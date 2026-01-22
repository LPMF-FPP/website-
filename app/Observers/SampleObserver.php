<?php

namespace App\Observers;

use App\Enums\SampleStatus;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Sample;
use App\Models\WhatsappOutbox;
use App\Services\WhatsApp\NotificationService;
use App\Support\ActivityLogger;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Arr;

class SampleObserver
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function created(Sample $sample): void
    {
        ActivityLogger::log(
            'SAMPLE_CREATED',
            null,
            $sample,
            null,
            $this->snapshot($sample),
            [
                'sample_code' => $sample->sample_code,
                'test_request_id' => $sample->test_request_id,
            ]
        );
    }

    public function updated(Sample $sample): void
    {
        $changes = $sample->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $before = Arr::only($sample->getOriginal(), array_keys($changes));

        ActivityLogger::log(
            'SAMPLE_UPDATED',
            null,
            $sample,
            $before,
            $changes,
            [
                'sample_code' => $sample->sample_code,
                'test_request_id' => $sample->test_request_id,
            ]
        );

        if (array_key_exists('sample_status', $changes)) {
            $this->handleStatusChange($sample, $changes['sample_status']);
        }
    }

    public function deleted(Sample $sample): void
    {
        ActivityLogger::log(
            'SAMPLE_DELETED',
            null,
            $sample,
            $this->snapshot($sample),
            null,
            [
                'sample_code' => $sample->sample_code,
                'test_request_id' => $sample->test_request_id,
            ]
        );
    }

    private function snapshot(Sample $sample): array
    {
        return [
            'id' => $sample->id,
            'sample_code' => $sample->sample_code,
            'test_request_id' => $sample->test_request_id,
            'status' => $sample->status,
            'sample_status' => $sample->sample_status,
            'assigned_analyst_id' => $sample->assigned_analyst_id,
        ];
    }

    private function handleStatusChange(Sample $sample, string $newStatus): void
    {
        if (! $this->notificationService->isWhatsAppEnabled()) {
            return;
        }

        $milestone = $this->mapStatusToMilestone($newStatus);

        if (! $milestone || ! $this->notificationService->shouldNotify($milestone)) {
            return;
        }

        $sample->load('testRequest.investigator');
        $testRequest = $sample->testRequest;

        if (! $testRequest || ! $testRequest->investigator) {
            return;
        }

        $phone = $testRequest->investigator->phone ?? null;

        if (! $phone) {
            return;
        }

        $jid = $this->notificationService->formatJID($phone);
        $greeting = $this->notificationService->getGreeting($testRequest->investigator);
        $timeBasedGreeting = $this->notificationService->getTimeBasedGreeting();
        $salutation = $this->notificationService->getSalutation($testRequest->investigator);
        $message = $this->notificationService->getMilestoneMessage($milestone, [
            'resi' => $testRequest->receipt_number,
            'nomor surat' => $testRequest->request_number,
            'tersangka' => $testRequest->suspect_name ?? '-',
            'pangkat' => $salutation,
            'nama' => $testRequest->investigator->name ?? '-',
            'greetings' => $timeBasedGreeting,
            'greeting' => $greeting,
        ]);

        if (! $message) {
            return;
        }

        $outbox = WhatsappOutbox::updateOrCreate(
            [
                'test_request_id' => $testRequest->id,
                'milestone_key' => $milestone,
            ],
            [
                'to_phone_e164' => PhoneNormalizer::toE164($phone),
                'to_jid' => $jid,
                'message_text' => $message,
                'status' => 'queued',
                'attempts' => 0,
            ]
        );

        SendWhatsAppNotificationJob::dispatch($outbox->id);
    }

    private function mapStatusToMilestone(string $status): ?string
    {
        return match ($status) {
            SampleStatus::ADMIN_DONE->value => 'REVIEW_DONE_READY_FOR_TEST',
            SampleStatus::PREPARATION_DONE->value => 'PREPARATION_DONE',
            SampleStatus::INSTRUMENTATION_DONE->value => 'INSTRUMENTATION_DONE',
            SampleStatus::INTERPRETATION_DONE->value => 'INTERPRETATION_DONE',
            SampleStatus::READY_FOR_DELIVERY->value => 'READY_FOR_PICKUP',
            default => null,
        };
    }
}
