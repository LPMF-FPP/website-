<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SampleStatus;
use App\Models\DeliveryReopening;
use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\InstrumentUsageLog;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Validation\ValidationException;

class ReopenedSampleRemovalService
{
    public function remove(TestRequest $request, Sample $sample, User $actor, string $reason): void
    {
        $reopening = DeliveryReopening::query()
            ->where('test_request_id', $request->id)
            ->where('sample_id', $sample->id)
            ->lockForUpdate()
            ->first();

        if (! $reopening) {
            throw ValidationException::withMessages([
                'remove_reopened_sample_ids' => 'Sampel ini tidak memiliki catatan pembukaan kembali yang dapat dikoreksi.',
            ]);
        }

        $lockedSample = Sample::query()->whereKey($sample->id)->lockForUpdate()->firstOrFail();
        if ((int) $lockedSample->test_request_id !== (int) $request->id
            || $lockedSample->status !== SampleStatus::FORM_SUBMITTED->value
            || $lockedSample->testProcesses()->exists()
            || $lockedSample->testResult()->exists()
            || Document::query()->where('sample_id', $lockedSample->id)->exists()
            || EvidenceUnit::query()->where('sample_id', $lockedSample->id)->exists()
            || InstrumentUsageLog::query()->where('sample_id', $lockedSample->id)->exists()
            || $lockedSample->disposal_id !== null) {
            throw ValidationException::withMessages([
                'remove_reopened_sample_ids' => 'Sampel sudah memiliki proses atau catatan turunan dan tidak dapat dihapus dari data aktif.',
            ]);
        }

        $snapshot = [
            'sample_id' => $lockedSample->id,
            'sample_code' => $lockedSample->sample_code,
            'short_description' => $lockedSample->short_description,
            'sample_form' => $lockedSample->sample_form,
            'sample_category' => $lockedSample->sample_category,
            'sample_type' => $lockedSample->sample_type,
            'sample_status' => $lockedSample->sample_status,
            'status' => $lockedSample->status,
            'sample_description' => $lockedSample->sample_description,
            'sample_weight' => $lockedSample->sample_weight,
            'package_quantity' => $lockedSample->package_quantity,
            'unit' => $lockedSample->unit,
            'condition' => $lockedSample->condition,
            'photo_path' => $lockedSample->photo_path,
            'receipt_path' => $lockedSample->receipt_path,
            'created_at' => $lockedSample->created_at?->toISOString(),
            'removed_at' => now()->toISOString(),
            'removed_by' => $actor->id,
            'removal_reason' => $reason,
        ];

        $reopening->update([
            'sample_id' => null,
            'sample_snapshot' => $snapshot,
        ]);

        $lockedSample->setRelation('testRequest', $request);
        $lockedSample->preserveSampleCodeSequenceOnDelete = true;
        $lockedSample->delete();

        ActivityLogger::log(
            'REOPENED_SAMPLE_REMOVED_FROM_ACTIVE_RECORDS',
            null,
            $request,
            ['sample_id' => $lockedSample->id, 'sample_code' => $lockedSample->sample_code],
            ['sample_id' => null, 'sample_code' => null],
            [
                'delivery_reopening_id' => $reopening->id,
                'handover_cycle' => $reopening->handover_cycle,
                'sample_snapshot' => $snapshot,
                'reason' => $reason,
            ],
            $actor->id
        );
    }
}
