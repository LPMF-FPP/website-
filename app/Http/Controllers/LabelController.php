<?php

namespace App\Http\Controllers;

use App\Models\EvidenceUnit;
use App\Models\LabelPrintLog;
use App\Models\RemainingUnit;
use App\Services\LabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LabelController extends Controller
{
    public function __construct(
        protected LabelService $labelService
    ) {
        $this->middleware(['auth']);
    }

    /**
     * Build explicit label payload from EvidenceUnit for DOMPDF.
     * Returns array with all fields explicitly mapped and QR as base64 PNG.
     */
    private function buildLabelPayload(EvidenceUnit $unit): array
    {
        // Get related sample for additional data
        $sample = $unit->sample;

        // Format received_at with safe fallback
        $receivedAt = $unit->received_at
            ?? $sample?->received_at
            ?? $unit->created_at;
        $receivedAtFormatted = $receivedAt
            ? Carbon::parse($receivedAt)->translatedFormat('d M Y H:i')
            : '-';

        // Get satuan (unit of measurement) from sample
        $satuan = $sample?->quantity_unit
            ?? $sample?->packaging_type
            ?? '-';

        // Generate QR as SVG base64 data URI (DOMPDF compatible, no imagick required)
        $qrContent = $unit->qr_content;
        $qrSvg = QrCode::size(200)
            ->margin(0)
            ->errorCorrection('M')
            ->generate($qrContent);
        $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        return [
            'id' => $unit->id,
            'resi' => $unit->receipt_code ?? '-',
            'kode_sampel' => $unit->sample_code ?? '-',
            'tanggal_terima' => $receivedAtFormatted,
            'penyidik' => $unit->investigator_name ?? '-',
            'satuan_kerja' => $unit->investigator_unit ?? '-',
            'satuan' => $satuan,
            'jenis' => $unit->sample_type ?? '-',
            'deskripsi' => $unit->sample_desc ?? '-',
            'segel' => $unit->seal_status_received ?? null,
            'kondisi' => $unit->condition_received ?? '-',
            'qr' => $qrDataUri,
            'qr_text' => $qrContent,
        ];
    }

    /**
     * Generate PDF sheet of evidence labels for a request.
     * GET /labels/evidence/request/{requestId}/sheet
     */
    public function evidenceSheet(Request $request, int $requestId)
    {
        $evidenceUnits = $this->labelService->getEvidenceUnitsForRequest($requestId);

        if ($evidenceUnits->isEmpty()) {
            return back()->with('error', 'Tidak ada label barang bukti untuk dicetak.');
        }

        $format = $request->query('size', 'a4');
        $reason = $request->query('reason', 'first_print');

        // Log the print
        foreach ($evidenceUnits as $eu) {
            $this->labelService->logPrint('evidence', $eu, $format, $reason);
        }

        // Build explicit label payloads with QR as base64 PNG
        $labels = $evidenceUnits->map(fn($unit) => $this->buildLabelPayload($unit))->values();

        // Debug log first label payload (remove after verification)
        logger()->info('Label payload sample', $labels->take(1)->toArray());

        $pdf = Pdf::loadView('labels.evidence-sheet', [
            'labels' => $labels,
            'printDate' => now()->translatedFormat('d M Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("label-barang-bukti-{$requestId}.pdf");
    }

    /**
     * Generate single evidence label PDF.
     * GET /labels/evidence/{id}/single
     */
    public function evidenceSingle(Request $request, int $id)
    {
        $evidenceUnit = EvidenceUnit::with('sample')->findOrFail($id);
        $reason = $request->query('reason', 'first_print');

        // Log the print
        $this->labelService->logPrint('evidence', $evidenceUnit, 'single', $reason);

        // Build explicit label payload with QR as base64 PNG
        $label = $this->buildLabelPayload($evidenceUnit);

        $pdf = Pdf::loadView('labels.evidence-single', [
            'label' => $label,
            'printDate' => now()->translatedFormat('d M Y H:i'),
        ]);

        $pdf->setPaper([0, 0, 283.46, 141.73], 'landscape'); // ~100x50mm

        return $pdf->stream("label-{$evidenceUnit->sample_code}.pdf");
    }

    /**
     * Generate PDF sheet of remaining labels for a request.
     * GET /labels/remaining/request/{requestId}/sheet
     */
    public function remainingSheet(Request $request, int $requestId)
    {
        $remainingUnits = $this->labelService->getRemainingUnitsForRequest($requestId);

        if ($remainingUnits->isEmpty()) {
            return back()->with('error', 'Tidak ada label sisa sampel untuk dicetak.');
        }

        $format = $request->query('size', 'a4');
        $reason = $request->query('reason', 'first_print');

        // Log the print
        foreach ($remainingUnits as $ru) {
            $this->labelService->logPrint('remaining', $ru, $format, $reason);
        }

        $pdf = Pdf::loadView('labels.remaining-sheet', [
            'remainingUnits' => $remainingUnits,
            'printDate' => now()->format('d M Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("label-sisa-{$requestId}.pdf");
    }

    /**
     * Generate remaining labels for a specific evidence unit.
     * GET /labels/remaining/{evidenceUnit}/all
     */
    public function remainingForEvidence(Request $request, int $evidenceUnitId)
    {
        $remainingUnits = $this->labelService->getRemainingUnitsForEvidence($evidenceUnitId);

        if ($remainingUnits->isEmpty()) {
            return back()->with('error', 'Tidak ada label sisa untuk barang bukti ini.');
        }

        $format = $request->query('size', 'a4');
        $reason = $request->query('reason', 'first_print');

        foreach ($remainingUnits as $ru) {
            $this->labelService->logPrint('remaining', $ru, $format, $reason);
        }

        $pdf = Pdf::loadView('labels.remaining-sheet', [
            'remainingUnits' => $remainingUnits,
            'printDate' => now()->format('d M Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("label-sisa-evidence-{$evidenceUnitId}.pdf");
    }

    /**
     * Generate single remaining label PDF.
     * GET /labels/remaining/{id}/single
     */
    public function remainingSingle(Request $request, int $id)
    {
        $remainingUnit = RemainingUnit::findOrFail($id);
        $reason = $request->query('reason', 'first_print');

        // Log the print
        $this->labelService->logPrint('remaining', $remainingUnit, 'single', $reason);

        $pdf = Pdf::loadView('labels.remaining-single', [
            'remainingUnit' => $remainingUnit,
            'printDate' => now()->format('d M Y H:i'),
        ]);

        $pdf->setPaper([0, 0, 283.46, 141.73], 'landscape'); // ~100x50mm

        return $pdf->stream("label-sisa-{$remainingUnit->remaining_code}.pdf");
    }
    /**
     * Create evidence units from sample IDs.
     * POST /labels/evidence-units
     */
    public function createEvidenceUnits(Request $request)
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
                'message' => $created->count() . ' label barang bukti berhasil dibuat.',
                'data' => $created->map(fn($eu) => [
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
     * POST /labels/remaining-units
     */
    public function createRemainingUnit(Request $request)
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
     * Delete a remaining unit.
     * DELETE /labels/remaining-units/{id}
     */
    public function destroyRemainingUnit(int $id)
    {
        try {
            $unit = RemainingUnit::findOrFail($id);
            $unit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Label sisa sampel berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus label: ' . $e->getMessage(),
            ], 500);
        }
    }
}
