<?php

namespace App\Services;

use App\Models\EvidenceUnit;
use App\Models\LabelPrintLog;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\TestRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LabelService
{
    /**
     * Ensure remaining labels are generated from request review quantities.
     *
     * Formula: qty_remaining = package_quantity - quantity
     */
    public function syncRemainingUnitsForRequest(TestRequest $request, ?int $actorId = null, ?array $sampleIds = null): int
    {
        $request->loadMissing(['investigator']);

        $samples = collect();
        if (is_array($sampleIds)) {
            $normalizedIds = array_values(array_unique(array_map(static fn ($id) => (int) $id, $sampleIds)));

            $samples = Sample::query()
                ->where('test_request_id', $request->id)
                ->whereIn('id', $normalizedIds)
                ->get();
        } elseif (! $request->relationLoaded('samples')) {
            $request->loadMissing(['samples']);
            $samples = $request->samples;
        } else {
            $samples = $request->samples;
        }

        $resolvedActorId = $actorId ?? Auth::id();
        $createdCount = 0;

        foreach ($samples as $sample) {
            $leftoverQty = $this->calculateRemainingQuantity($sample);

            $evidenceUnit = EvidenceUnit::query()->where('sample_id', $sample->id)->first();

            if ($leftoverQty === null || $leftoverQty <= 0) {
                if ($evidenceUnit) {
                    RemainingUnit::query()
                        ->where('evidence_unit_id', $evidenceUnit->id)
                        ->delete();
                }

                continue;
            }

            if (! $evidenceUnit) {
                $evidenceUnit = EvidenceUnit::query()->firstOrCreate(
                    ['sample_id' => $sample->id],
                    [
                        'request_id' => $request->id,
                        'sample_code' => $sample->sample_code,
                        'sample_type' => $sample->sample_category ?? $sample->sample_form,
                        'sample_desc' => $sample->short_description ?? $sample->sample_description,
                        'investigator_name' => $request->investigator?->name,
                        'investigator_unit' => $request->investigator?->jurisdiction,
                        'received_at' => $sample->received_at ?? $request->received_at ?? now(),
                        'received_by' => $resolvedActorId,
                    ]
                );
            }

            $remainingUnit = RemainingUnit::query()
                ->updateOrCreate(
                    ['evidence_unit_id' => $evidenceUnit->id],
                    [
                        'sample_code' => $sample->sample_code,
                        'qty_remaining' => $leftoverQty,
                        'uom' => $sample->unit ?? $sample->quantity_unit,
                        'seal_status_delivered' => 'disegel',
                        'delivered_at' => now(),
                        'delivered_by' => $resolvedActorId,
                    ]
                );

            if ($remainingUnit->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        return $createdCount;
    }

    /**
     * Create evidence units from sample IDs.
     *
     * @param  int  $requestId  Test request ID
     * @param  array  $sampleIds  Array of sample IDs to create labels for
     * @return Collection Created EvidenceUnit records
     *
     * @throws RuntimeException if samples don't belong to request
     */
    public function createEvidenceUnits(int $requestId, array $sampleIds): Collection
    {
        $request = TestRequest::with('investigator')->findOrFail($requestId);

        // Validate all samples belong to this request
        $samples = Sample::whereIn('id', $sampleIds)
            ->where('test_request_id', $requestId)
            ->get();

        if ($samples->count() !== count($sampleIds)) {
            throw new RuntimeException('Some samples do not belong to this request.');
        }

        $created = collect();

        DB::transaction(function () use ($request, $samples, &$created) {
            foreach ($samples as $sample) {
                // Skip if evidence unit already exists for this sample
                if (EvidenceUnit::where('sample_id', $sample->id)->exists()) {
                    continue;
                }

                $evidenceUnit = EvidenceUnit::create([
                    'request_id' => $request->id,
                    'sample_id' => $sample->id,
                    'receipt_code' => $request->receipt_number,
                    'sample_code' => $sample->sample_code,
                    'sample_type' => $sample->sample_category ?? $sample->sample_form,
                    'sample_desc' => $sample->short_description ?? $sample->sample_description,
                    'investigator_name' => $request->investigator?->name ?? $request->investigator?->rank_name,
                    'investigator_unit' => $request->investigator?->satuan_kerja ?? $request->investigator?->unit,
                    'seal_status_received' => null, // To be filled during physical receipt
                    'condition_received' => $sample->condition,
                    'received_at' => $sample->received_at ?? $request->received_at,
                    'received_by' => $sample->received_by ?? Auth::id(),
                ]);

                $created->push($evidenceUnit);
            }
        });

        return $created;
    }

    /**
     * Create a remaining unit for an evidence unit.
     *
     * @param  int  $evidenceUnitId  The evidence unit ID
     * @param  array  $data  Remaining unit data
     * @return RemainingUnit Created remaining unit
     */
    public function createRemainingUnit(int $evidenceUnitId, array $data): RemainingUnit
    {
        $evidenceUnit = EvidenceUnit::findOrFail($evidenceUnitId);

        return RemainingUnit::query()->updateOrCreate(
            ['evidence_unit_id' => $evidenceUnitId],
            [
                'sample_code' => $evidenceUnit->sample_code, // Will be set by model if empty
                // remaining_code is auto-generated by model
                'qty_remaining' => $data['qty_remaining'] ?? null,
                'uom' => $data['uom'] ?? null,
                'seal_status_delivered' => $data['seal_status_delivered'] ?? null,
                'condition_delivered' => $data['condition_delivered'] ?? null,
                'delivered_at' => $data['delivered_at'] ?? now(),
                'delivered_by' => $data['delivered_by'] ?? Auth::id(),
                'handover_doc_no' => $data['handover_doc_no'] ?? null,
            ]
        );
    }

    /**
     * Get all evidence units for a request.
     */
    public function getEvidenceUnitsForRequest(int $requestId): Collection
    {
        return EvidenceUnit::with(['sample', 'receivedBy', 'remainingUnits'])
            ->where('request_id', $requestId)
            ->orderBy('sample_code')
            ->get();
    }

    /**
     * Get all remaining units for an evidence unit.
     */
    public function getRemainingUnitsForEvidence(int $evidenceUnitId): Collection
    {
        return RemainingUnit::with(['deliveredBy'])
            ->where('evidence_unit_id', $evidenceUnitId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get all remaining units for a request (all samples).
     */
    public function getRemainingUnitsForRequest(int $requestId): Collection
    {
        $evidenceUnitIds = EvidenceUnit::where('request_id', $requestId)->pluck('id');

        return RemainingUnit::with(['evidenceUnit', 'deliveredBy'])
            ->whereIn('evidence_unit_id', $evidenceUnitIds)
            ->orderBy('sample_code')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Log a print action.
     */
    public function logPrint(
        string $labelType,
        $printable,
        string $format = 'a4',
        ?string $reason = null,
        int $count = 1
    ): LabelPrintLog {
        return LabelPrintLog::logPrint(
            $labelType,
            $printable,
            Auth::id(),
            $format,
            $reason,
            $count
        );
    }

    /**
     * Get print history for a printable item.
     */
    public function getPrintHistory($printable): Collection
    {
        return $printable->printLogs()
            ->with('printedBy')
            ->orderByDesc('created_at')
            ->get();
    }

    private function calculateRemainingQuantity(Sample $sample): ?float
    {
        $deliveredQty = $sample->package_quantity;
        $testingQty = $sample->quantity;

        if ($deliveredQty !== null && ! is_numeric($deliveredQty)) {
            return null;
        }

        if ($testingQty !== null && ! is_numeric($testingQty)) {
            $testingQty = null;
        }

        if ($deliveredQty === null) {
            return null;
        }

        if ($testingQty === null) {
            return null;
        }

        $diff = (float) $deliveredQty - (float) $testingQty;

        return $diff > 0 ? $diff : 0.0;
    }
}
