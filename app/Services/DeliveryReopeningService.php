<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\SampleStatus;
use App\Models\Delivery;
use App\Models\DeliveryReopening;
use App\Models\Document;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappOutbox;
use App\Services\WhatsApp\MilestoneNotificationService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryReopeningService
{
    public function reopen(TestRequest $request, User $actor, string $reason, bool $collectionNotTakenConfirmed, array $sampleData): DeliveryReopening
    {
        return DB::transaction(function () use ($request, $actor, $reason, $collectionNotTakenConfirmed, $sampleData): DeliveryReopening {
            if (! $collectionNotTakenConfirmed) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Konfirmasi bahwa hasil belum diambil wajib disetujui.',
                ]);
            }

            $lockedRequest = TestRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($lockedRequest->status, ['ready_for_delivery', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'reopen_reason' => 'Hanya permintaan siap diserahkan atau selesai secara administratif yang dapat dibuka kembali sebelum hasil diambil.',
                ]);
            }

            $delivery = Delivery::query()
                ->where('request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                $delivery = Delivery::query()->create([
                    'request_id' => $lockedRequest->id,
                    'delivered_by' => $actor->id,
                    'delivery_date' => now(),
                    'status' => DeliveryStatus::PENDING,
                    'handover_cycle' => 1,
                ]);
            }

            if ($delivery->hasBeenCollected()) {
                throw ValidationException::withMessages([
                    'reopen_reason' => 'Hasil sudah diambil. Buat permintaan suplemen tertaut agar riwayat penyerahan awal tetap utuh.',
                ]);
            }

            if (! $delivery->status->canTransitionTo(DeliveryStatus::REOPENED)) {
                throw ValidationException::withMessages([
                    'reopen_reason' => 'Status penyerahan saat ini tidak mendukung pembukaan kembali.',
                ]);
            }

            $nextCycle = max(1, (int) $delivery->handover_cycle) + 1;
            $supersededDocuments = Document::query()
                ->where('test_request_id', $lockedRequest->id)
                ->where('source', 'generated')
                ->whereIn('document_type', ['ba_penyerahan', 'ba_penyerahan_html'])
                ->whereNull('extra->superseded_at')
                ->get();
            $supersededDocumentIds = $supersededDocuments->pluck('id')->map(fn ($id): int => (int) $id)->all();
            foreach ($supersededDocuments as $document) {
                $document->forceFill([
                    'extra' => array_merge($document->extra ?? [], [
                        'superseded_at' => now()->toISOString(),
                        'superseded_reason' => 'Permintaan dibuka kembali untuk sampel tambahan.',
                        'superseded_handover_cycle' => (int) $delivery->handover_cycle,
                    ]),
                ])->save();
            }
            $outboxIds = WhatsappOutbox::query()
                ->where('test_request_id', $lockedRequest->id)
                ->whereIn('milestone_key', ['READY_FOR_PICKUP', 'HANDOVER_COMPLETED'])
                ->pluck('id');
            $cycleMessageLogs = $outboxIds->isEmpty()
                ? collect()
                : WhatsAppMessageLog::query()
                    ->where('source_type', WhatsappOutbox::class)
                    ->whereIn('source_id', $outboxIds)
                    ->lockForUpdate()
                    ->get();

            $uncertainMessage = $cycleMessageLogs->first(fn (WhatsAppMessageLog $messageLog): bool => in_array($messageLog->status, [
                WhatsAppMessageLog::STATUS_SENDING,
                WhatsAppMessageLog::STATUS_UNKNOWN,
            ], true));

            if ($uncertainMessage) {
                throw ValidationException::withMessages([
                    'reopen_reason' => 'Status notifikasi siklus lama belum pasti. Periksa Log Pengiriman WhatsApp dan penerima sebelum membuka kembali penyerahan.',
                ]);
            }

            $supersededMessageLogIds = $cycleMessageLogs->pluck('id')->map(fn ($id): int => (int) $id)->all();
            foreach ($cycleMessageLogs as $messageLog) {
                if (! in_array($messageLog->status, [
                    WhatsAppMessageLog::STATUS_PREPARING,
                    WhatsAppMessageLog::STATUS_PENDING,
                ], true)) {
                    continue;
                }

                $messageLog->update([
                    'status' => WhatsAppMessageLog::STATUS_BLOCKED,
                    'error_message' => 'Notifikasi dibatalkan karena siklus penyerahan dibuka kembali sebelum hasil diambil.',
                    'retryable' => false,
                    'retry_block_reason' => 'Siklus handover lama superseded; gunakan notifikasi pada siklus aktif.',
                    'claimed_at' => null,
                    'completed_at' => now(),
                ]);
                app(MilestoneNotificationService::class)->syncOutboxForMessageLog($messageLog);
            }

            $sample = Sample::query()->create([
                'test_request_id' => $lockedRequest->id,
                'short_description' => $sampleData['short_description'],
                'sample_form' => $sampleData['sample_form'],
                'sample_category' => $sampleData['sample_category'],
                'other_sample_category' => $sampleData['sample_category'] === 'other'
                    ? ($sampleData['other_sample_category'] ?? null)
                    : null,
                'sample_description' => $sampleData['sample_description'] ?? null,
                'sample_weight' => $sampleData['sample_weight'] ?? null,
                'package_quantity' => $sampleData['package_quantity'],
                'unit' => $sampleData['unit'],
                'condition' => $sampleData['condition'],
                'sample_status' => 'received',
                'status' => SampleStatus::FORM_SUBMITTED->value,
                'test_methods' => null,
                'requested_test_methods' => null,
                'active_substance' => null,
            ]);

            $reopening = DeliveryReopening::query()->create([
                'test_request_id' => $lockedRequest->id,
                'delivery_id' => $delivery->id,
                'sample_id' => $sample->id,
                'reopened_by' => $actor->id,
                'handover_cycle' => $nextCycle,
                'previous_request_status' => (string) $lockedRequest->status,
                'previous_delivery_status' => $delivery->status?->value ?? (string) $delivery->status,
                'previous_delivery_date' => $delivery->delivery_date,
                'previous_ready_for_delivery_at' => $lockedRequest->ready_for_delivery_at,
                'previous_completed_at' => $lockedRequest->completed_at,
                'reason' => $reason,
                'superseded_document_ids' => $supersededDocumentIds,
                'superseded_message_log_ids' => $supersededMessageLogIds,
                'reopened_at' => now(),
            ]);

            $delivery->forceFill([
                'status' => DeliveryStatus::REOPENED,
                'handover_cycle' => $nextCycle,
                'delivery_date' => now(),
                'delivered_by' => $actor->id,
            ])->save();

            $lockedRequest->update([
                'status' => 'in_testing',
                'ready_for_delivery_at' => null,
                'completed_at' => null,
            ]);

            ActivityLogger::log(
                'DELIVERY_REOPENED_FOR_ADDITIONAL_SAMPLE',
                null,
                $lockedRequest,
                [
                    'status' => $reopening->previous_request_status,
                    'delivery_status' => $reopening->previous_delivery_status,
                ],
                [
                    'status' => $lockedRequest->status,
                    'delivery_status' => $delivery->status->value,
                ],
                [
                    'delivery_reopening_id' => $reopening->id,
                    'handover_cycle' => $nextCycle,
                    'reason' => $reason,
                    'sample_id' => $sample->id,
                    'superseded_document_ids' => $supersededDocumentIds,
                    'superseded_message_log_ids' => $supersededMessageLogIds,
                ],
                $actor->id
            );

            return $reopening;
        }, attempts: 3);
    }
}
