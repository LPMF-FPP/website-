<?php

namespace App\Observers;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\TestRequest;
use App\Models\WhatsappOutbox;
use App\Services\NumberingService;
use App\Services\WhatsApp\NotificationService;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;

class TestRequestObserver
{
    public function __construct(
        private NotificationService $notificationService,
        private NumberingService $numberingService
    ) {
    }

    public function created(TestRequest $testRequest): void
    {
        ActivityLogger::log(
            'REQUEST_CREATED',
            null,
            $testRequest,
            null,
            $this->snapshot($testRequest),
            [
                'request_number' => $testRequest->request_number,
                'receipt_number' => $testRequest->receipt_number,
            ]
        );

        $this->sendWhatsAppNotification($testRequest, 'REQUEST_RECEIVED');
    }

    public function updated(TestRequest $testRequest): void
    {
        $changes = $testRequest->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $before = Arr::only($testRequest->getOriginal(), array_keys($changes));
        $action = 'REQUEST_UPDATED';
        $meta = [
            'request_number' => $testRequest->request_number,
            'receipt_number' => $testRequest->receipt_number,
        ];

        if (array_key_exists('status', $changes)) {
            $action = 'REQUEST_STATUS_CHANGED';
            $meta['changes'] = Arr::except($changes, ['status']);
            $before = ['status' => $before['status'] ?? null];
            $changes = ['status' => $changes['status']];

            if ($testRequest->status === 'completed') {
                $this->sendWhatsAppNotification($testRequest, 'HANDOVER_COMPLETED');
            }

            if ($testRequest->status === 'rejected') {
                $this->sendWhatsAppNotification($testRequest, 'REQUEST_REJECTED', [
                    'reason' => $testRequest->rejected_reason ?? '-',
                ]);
            }
        }

        ActivityLogger::log(
            $action,
            null,
            $testRequest,
            $before,
            $changes,
            $meta
        );
    }

    public function deleted(TestRequest $testRequest): void
    {
        // Attempt to rollback numbers if they are the latest in sequence
        if ($testRequest->request_number) {
            $this->numberingService->rollback('ba', $testRequest->request_number, [
                'investigator_id' => $testRequest->investigator_id,
                'now' => $testRequest->created_at, // Use creation time to match bucket
            ]);
        }

        if ($testRequest->receipt_number) {
            $this->numberingService->rollback('tracking', $testRequest->receipt_number, [
                'investigator_id' => $testRequest->investigator_id,
                'now' => $testRequest->created_at,
            ]);
        }

        ActivityLogger::log(
            'REQUEST_DELETED',
            null,
            $testRequest,
            $this->snapshot($testRequest),
            null,
            [
                'request_number' => $testRequest->request_number,
                'receipt_number' => $testRequest->receipt_number,
            ]
        );
    }

    private function snapshot(TestRequest $testRequest): array
    {
        return [
            'id' => $testRequest->id,
            'request_number' => $testRequest->request_number,
            'receipt_number' => $testRequest->receipt_number,
            'status' => $testRequest->status,
            'investigator_id' => $testRequest->investigator_id,
            'user_id' => $testRequest->user_id,
            'submitted_at' => optional($testRequest->submitted_at)->toISOString(),
            'verified_at' => optional($testRequest->verified_at)->toISOString(),
            'received_at' => optional($testRequest->received_at)->toISOString(),
            'completed_at' => optional($testRequest->completed_at)->toISOString(),
        ];
    }

    private function sendWhatsAppNotification(TestRequest $testRequest, string $milestone, array $extraReplacements = []): void
    {
        if (!$this->notificationService->isWhatsAppEnabled()) {
            return;
        }

        if (!$this->notificationService->shouldNotify($milestone)) {
            return;
        }

        $testRequest->load('investigator');

        if (!$testRequest->investigator) {
            return;
        }

        $phone = $testRequest->investigator->phone ?? null;

        if (!$phone) {
            return;
        }

        $jid = $this->notificationService->formatJID($phone);
        $greeting = $this->notificationService->getGreeting($testRequest->investigator);
        $timeBasedGreeting = $this->notificationService->getTimeBasedGreeting();
        $salutation = $this->notificationService->getSalutation($testRequest->investigator);

        $message = $this->notificationService->getMilestoneMessage($milestone, array_merge([
            'resi' => $testRequest->receipt_number,
            'nomor surat' => $testRequest->request_number,
            'tersangka' => $testRequest->suspect_name ?? '-',
            'pangkat' => $salutation,
            'nama' => $testRequest->investigator->name ?? '-',
            'greetings' => $timeBasedGreeting,
            'greeting' => $greeting,
        ], $extraReplacements));

        if (!$message) {
            return;
        }

        $outbox = WhatsappOutbox::updateOrCreate(
            [
                'test_request_id' => $testRequest->id,
                'milestone_key' => $milestone,
            ],
            [
                'to_phone_e164' => \App\Support\PhoneNormalizer::toE164($phone),
                'to_jid' => $jid,
                'message_text' => $message,
                'status' => 'queued',
                'attempts' => 0,
            ]
        );

        SendWhatsAppNotificationJob::dispatch($outbox->id);
    }
}
