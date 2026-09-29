<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SampleStatus;
use App\Models\DeliveryReopening;
use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\InstrumentUsageLog;
use App\Models\LabelPrintLog;
use App\Models\RemainingUnit;
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
        $evidenceUnits = EvidenceUnit::query()
            ->where('sample_id', $lockedSample->id)
            ->lockForUpdate()
            ->get();
        $remainingUnits = RemainingUnit::query()
            ->whereIn('evidence_unit_id', $evidenceUnits->pluck('id'))
            ->lockForUpdate()
            ->get();

        $hasPrintedLabels = LabelPrintLog::query()
            ->where(function ($query) use ($evidenceUnits, $remainingUnits): void {
                $query->where(function ($query) use ($evidenceUnits): void {
                    $query->where('printable_type', (new EvidenceUnit)->getMorphClass())
                        ->whereIn('printable_id', $evidenceUnits->pluck('id'));
                })->orWhere(function ($query) use ($remainingUnits): void {
                    $query->where('printable_type', (new RemainingUnit)->getMorphClass())
                        ->whereIn('printable_id', $remainingUnits->pluck('id'));
                });
            })
            ->lockForUpdate()
            ->exists();

        $hasPhysicalEvidenceRecord = $evidenceUnits->contains(
            fn (EvidenceUnit $unit): bool => (int) $unit->request_id !== (int) $request->id
                || $unit->sample_code !== $lockedSample->sample_code
                || $unit->received_at !== null
                || $unit->seal_status_received !== null
        ) || $remainingUnits->count() > 1
            || $remainingUnits->contains(
                fn (RemainingUnit $unit): bool => $unit->sample_code !== $lockedSample->sample_code
                    || $unit->handover_doc_no !== null
                    || $unit->condition_delivered !== null
            );

        if ((int) $lockedSample->test_request_id !== (int) $request->id
            || $lockedSample->status !== SampleStatus::FORM_SUBMITTED->value
            || $lockedSample->testProcesses()->exists()
            || $lockedSample->testResult()->exists()
            || Document::query()->where('sample_id', $lockedSample->id)->exists()
            || InstrumentUsageLog::query()->where('sample_id', $lockedSample->id)->exists()
            || $lockedSample->disposal_id !== null
            || $hasPrintedLabels
            || $hasPhysicalEvidenceRecord) {
            throw ValidationException::withMessages([
                'remove_reopened_sample_ids' => 'Sampel sudah memiliki proses, label tercetak, atau catatan serah terima fisik dan tidak dapat dihapus dari data aktif.',
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
            'removed_evidence_units' => $evidenceUnits->map(fn (EvidenceUnit $unit): array => [
                'id' => $unit->id,
                'sample_code' => $unit->sample_code,
                'receipt_code' => $unit->receipt_code,
                'created_at' => $unit->created_at?->toISOString(),
            ])->all(),
            'removed_remaining_units' => $remainingUnits->map(fn (RemainingUnit $unit): array => [
                'id' => $unit->id,
                'sample_code' => $unit->sample_code,
                'remaining_code' => $unit->remaining_code,
                'qty_remaining' => $unit->qty_remaining,
                'uom' => $unit->uom,
                'created_at' => $unit->created_at?->toISOString(),
            ])->all(),
        ];

        $reopening->update([
            'sample_id' => null,
            'sample_snapshot' => $snapshot,
        ]);

        RemainingUnit::query()->whereKey($remainingUnits->pluck('id'))->delete();
        EvidenceUnit::query()->whereKey($evidenceUnits->pluck('id'))->delete();

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
