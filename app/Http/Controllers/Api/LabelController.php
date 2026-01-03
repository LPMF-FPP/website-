<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidenceUnit;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function __construct(
        protected LabelService $labelService
    ) {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * Create evidence units from sample IDs.
     * POST /api/labels/evidence-units
     */
    public function createEvidenceUnits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'exists:test_requests,id'],
            'sample_ids' => ['required', 'array', 'min:1'],
            'sample_ids.*' => ['required', 'exists:samples,id'],
        ]);

        try {
            $created = $this->labelService->createEvidenceUnits(
                $validated['request_id'],
                $validated['sample_ids']
            );

            return response()->json([
                'success' => true,
                'message' => $created->count().' label barang bukti berhasil dibuat.',
                'data' => $created->map(fn ($eu) => [
                    'id' => $eu->id,
                    'sample_id' => $eu->sample_id,
                    'sample_code' => $eu->sample_code,
                    'qr_content' => $eu->qr_content,
                ]),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create a remaining unit for an evidence unit.
     * POST /api/labels/remaining-units
     */
    public function createRemainingUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'evidence_unit_id' => ['required', 'exists:evidence_units,id'],
            'qty_remaining' => ['nullable', 'numeric', 'min:0'],
            'uom' => ['nullable', 'string', 'max:50'],
            'seal_status_delivered' => ['nullable', 'string', 'max:100'],
            'condition_delivered' => ['nullable', 'string', 'max:100'],
            'handover_doc_no' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $remaining = $this->labelService->createRemainingUnit(
                $validated['evidence_unit_id'],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Label sisa sampel berhasil dibuat.',
                'data' => [
                    'id' => $remaining->id,
                    'sample_code' => $remaining->sample_code,
                    'remaining_code' => $remaining->remaining_code,
                    'qr_content' => $remaining->qr_content,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get evidence units for a request.
     * GET /api/labels/evidence-units/{requestId}
     */
    public function getEvidenceUnits(int $requestId): JsonResponse
    {
        $evidenceUnits = $this->labelService->getEvidenceUnitsForRequest($requestId);

        return response()->json([
            'success' => true,
            'data' => $evidenceUnits->map(fn ($eu) => [
                'id' => $eu->id,
                'sample_id' => $eu->sample_id,
                'sample_code' => $eu->sample_code,
                'sample_type' => $eu->sample_type,
                'sample_desc' => $eu->sample_desc,
                'investigator_name' => $eu->investigator_name,
                'received_at' => $eu->received_at_formatted,
                'remaining_count' => $eu->remainingUnits->count(),
                'qr_content' => $eu->qr_content,
            ]),
        ]);
    }

    /**
     * Get remaining units for an evidence unit.
     * GET /api/labels/remaining-units/{evidenceUnitId}
     */
    public function getRemainingUnits(int $evidenceUnitId): JsonResponse
    {
        $remainingUnits = $this->labelService->getRemainingUnitsForEvidence($evidenceUnitId);

        return response()->json([
            'success' => true,
            'data' => $remainingUnits->map(fn ($ru) => [
                'id' => $ru->id,
                'sample_code' => $ru->sample_code,
                'remaining_code' => $ru->remaining_code,
                'qty_with_uom' => $ru->qty_with_uom,
                'delivered_at' => $ru->delivered_at_formatted,
                'handover_doc_no' => $ru->handover_doc_no,
                'qr_content' => $ru->qr_content,
            ]),
        ]);
    }

    /**
     * Get samples available for evidence unit creation.
     * GET /api/labels/available-samples/{requestId}
     */
    public function getAvailableSamples(int $requestId): JsonResponse
    {
        $existingSampleIds = EvidenceUnit::where('request_id', $requestId)
            ->pluck('sample_id')
            ->toArray();

        $samples = \App\Models\Sample::where('test_request_id', $requestId)
            ->whereNotIn('id', $existingSampleIds)
            ->orderBy('sample_code')
            ->get(['id', 'sample_code', 'short_description', 'sample_description']);

        return response()->json([
            'success' => true,
            'data' => $samples,
        ]);
    }
}
