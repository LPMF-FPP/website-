<?php

namespace App\Http\Controllers;

use App\Models\EvidenceUnit;
use App\Models\RemainingUnit;
use App\Models\TestRequest;
use App\Services\LabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
            ?? $sample?->unit
            ?? '-';

        // Generate QR as PNG data URI for stable rendering in DOMPDF and previews
        $qrContent = $unit->qr_content;
        $qrDataUri = $this->qrPngDataUri($qrContent);

        return [
            'id' => $unit->id,
            'resi' => $unit->receipt_code ?? '-',
            'kode_sampel' => $unit->sample_code ?? '-',
            'tanggal_terima' => $receivedAtFormatted,
            'deskripsi_singkat' => $sample?->short_description ?? '-',
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
     * Generate a QR PNG data URI for a given text.
     */
    private function qrPngDataUri(?string $text): string
    {
        if (! $text) {
            return '';
        }

        try {
            $png = QrCode::format('png')
                ->size(200)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($text);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable $e) {
            $svg = QrCode::format('svg')
                ->size(200)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($text);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        }
    }

    /**
     * Build explicit case label payload (for right column).
     */
    private function buildCaseLabelPayload(TestRequest $request): array
    {
        $sampleCodes = $request->samples->sortBy('id')
            ->pluck('sample_code')
            ->filter()
            ->values();

        $compactRange = $this->buildCompactSampleCodeRange($sampleCodes);

        $allSampleCodes = match (true) {
            $sampleCodes->isEmpty() => '-',
            $sampleCodes->count() <= 4 => $sampleCodes->implode(', '),
            $compactRange !== null => $compactRange,
            default => sprintf('%d sampel', $sampleCodes->count()),
        };

        return [
            'nama_tsk' => $request->suspect_name ?? '-',
            'nomor_surat' => $request->case_number ?? '-',
            'satuan_kerja' => $request->investigator->jurisdiction ?? '-',
            'daftar_kode_sampel' => $allSampleCodes,
            'resi' => $request->receipt_number ?? '-',
            'print_footer' => false,
        ];
    }

    private function buildCompactSampleCodeRange(\Illuminate\Support\Collection $sampleCodes): ?string
    {
        if ($sampleCodes->count() <= 1) {
            return null;
        }

        $parsedCodes = $sampleCodes->map(function ($code) {
            if (preg_match('/^(.*?)(\d+)$/', (string) $code, $matches) !== 1) {
                return null;
            }

            return [
                'prefix' => $matches[1],
                'number' => (int) $matches[2],
                'digits' => strlen($matches[2]),
            ];
        });

        if ($parsedCodes->contains(null)) {
            return null;
        }

        $first = $parsedCodes->first();

        if (! is_array($first)) {
            return null;
        }

        $samePrefix = $parsedCodes->every(fn ($parsed) => is_array($parsed) && $parsed['prefix'] === $first['prefix']);
        $sameDigits = $parsedCodes->every(fn ($parsed) => is_array($parsed) && $parsed['digits'] === $first['digits']);

        if (! $samePrefix || ! $sameDigits) {
            return null;
        }

        $numbers = $parsedCodes->pluck('number')->values();
        $expectedRange = range((int) $numbers->first(), (int) $numbers->last());

        if ($numbers->all() !== $expectedRange) {
            return null;
        }

        $firstNumber = str_pad((string) $numbers->first(), $first['digits'], '0', STR_PAD_LEFT);
        $lastNumber = str_pad((string) $numbers->last(), $first['digits'], '0', STR_PAD_LEFT);

        return sprintf('%s%s-%s (%d sampel)', $first['prefix'], $firstNumber, $lastNumber, $sampleCodes->count());
    }

    /**
     * Generate PDF sheet of evidence labels for a request.
     * GET /labels/evidence/request/{requestId}/sheet
     */
    public function evidenceSheet(Request $request, int $requestId)
    {
        $testRequest = TestRequest::with(['samples', 'investigator'])->find($requestId);

        // Keep label inventory in sync with current request samples before printing.
        $evidenceUnits = $this->labelService->ensureEvidenceUnitsForRequest($requestId);

        if ($evidenceUnits->isEmpty()) {
            return back()->with('error', 'Tidak ada label barang bukti untuk dicetak.');
        }

        $format = $request->query('size', 'a4');
        $reason = $request->query('reason', 'first_print');

        // Log the print
        foreach ($evidenceUnits as $eu) {
            $this->labelService->logPrint('evidence', $eu, $format, $reason);
        }

        // Build left column payloads (Evidence Labels)
        $evidencePayloads = $evidenceUnits->map(fn ($unit) => $this->buildLabelPayload($unit))->values();

        // Build right column payload (Case Label) - Fixed single payload
        $casePayload = $this->buildCaseLabelPayload($testRequest);

        $rows = [];

        for ($i = 0; $i < 4; $i++) {
            $row = [
                'left' => $evidencePayloads->get($i),
                'right' => array_merge($casePayload, ['kind' => 'case']),
            ];

            $rows[] = $row;
        }

        $pages = collect([
            [
                'layout' => 'mixed',
                'rows' => collect($rows),
            ],
        ]);

        $remainingEvidencePayloads = $evidencePayloads->slice(4)->values();

        foreach ($remainingEvidencePayloads->chunk(8) as $chunk) {
            $gridRows = [];

            for ($i = 0; $i < 4; $i++) {
                $left = $chunk->get($i * 2);
                $right = $chunk->get(($i * 2) + 1);

                if (! $left && ! $right) {
                    continue;
                }

                $gridRows[] = [
                    'left' => $left,
                    'right' => $right ? array_merge($right, ['kind' => 'evidence']) : null,
                ];
            }

            if ($gridRows !== []) {
                $pages->push([
                    'layout' => 'evidence-grid',
                    'rows' => collect($gridRows),
                ]);
            }
        }

        $pdf = Pdf::loadView('labels.evidence-sheet', [
            'pages' => $pages,
            'request' => $testRequest, // Pass request for checklist
            'printDate' => now()->translatedFormat('d M Y H:i'),
        ]);

        // Custom size for Label 121: 165mm x 210mm
        // 165mm = 467.72 pt, 210mm = 595.28 pt
        $pdf->setPaper([0, 0, 467.72, 595.28], 'portrait');

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

        // 75mm x 38mm in points (1mm = 2.83465pt)
        // 75mm = 212.60 pt
        // 38mm = 107.72 pt
        $pdf->setPaper([0, 0, 212.60, 107.72], 'landscape');

        return $pdf->stream("label-{$evidenceUnit->sample_code}.pdf");
    }

    private function canAccessRemainingLabelFeature(?string $status): bool
    {
        return in_array($status, [
            'in_testing',
            'ready_for_delivery',
            'completed',
        ], true);
    }

    private function denyRemainingLabelAccessMessage(): string
    {
        return 'Cetak label sisa tersedia setelah kaji ulang permintaan selesai.';
    }

    private function ensureRemainingLabelAccessForRequest(int $requestId): ?TestRequest
    {
        $testRequest = TestRequest::query()->select(['id', 'status'])->find($requestId);

        if (! $testRequest || ! $this->canAccessRemainingLabelFeature($testRequest->status)) {
            return null;
        }

        return $testRequest;
    }

    private function ensureRemainingLabelAccessForEvidenceUnit(int $evidenceUnitId): ?EvidenceUnit
    {
        $evidenceUnit = EvidenceUnit::query()
            ->select(['id', 'request_id'])
            ->with(['request:id,status'])
            ->find($evidenceUnitId);

        if (! $evidenceUnit || ! $this->canAccessRemainingLabelFeature($evidenceUnit->request?->status)) {
            return null;
        }

        return $evidenceUnit;
    }

    private function ensureRemainingLabelAccessForRemainingUnit(int $remainingUnitId): ?RemainingUnit
    {
        $remainingUnit = RemainingUnit::query()
            ->select(['id', 'evidence_unit_id', 'remaining_code', 'qr_token'])
            ->with(['evidenceUnit:id,request_id', 'evidenceUnit.request:id,status'])
            ->find($remainingUnitId);

        if (! $remainingUnit || ! $this->canAccessRemainingLabelFeature($remainingUnit->evidenceUnit?->request?->status)) {
            return null;
        }

        return $remainingUnit;
    }

    /**
     * Generate PDF sheet of remaining labels for a request.
     * GET /labels/remaining/request/{requestId}/sheet
     */
    public function remainingSheet(Request $request, int $requestId)
    {
        if (! $this->ensureRemainingLabelAccessForRequest($requestId)) {
            return back()->with('error', $this->denyRemainingLabelAccessMessage());
        }

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

        $remainingUnits->each(function ($unit) {
            $unit->qr_png = $this->qrPngDataUri($unit->qr_content);
        });

        $pdf = Pdf::loadView('labels.remaining-sheet', [
            'remainingUnits' => $remainingUnits,
            'printDate' => now()->format('d M Y H:i'),
        ]);

        // Custom size for Label 121: 165mm x 210mm
        $pdf->setPaper([0, 0, 467.72, 595.28], 'portrait');

        return $pdf->stream("label-sisa-{$requestId}.pdf");
    }

    /**
     * Generate remaining labels for a specific evidence unit.
     * GET /labels/remaining/{evidenceUnit}/all
     */
    public function remainingForEvidence(Request $request, int $evidenceUnitId)
    {
        if (! $this->ensureRemainingLabelAccessForEvidenceUnit($evidenceUnitId)) {
            return back()->with('error', $this->denyRemainingLabelAccessMessage());
        }

        $remainingUnits = $this->labelService->getRemainingUnitsForEvidence($evidenceUnitId);

        if ($remainingUnits->isEmpty()) {
            return back()->with('error', 'Tidak ada label sisa untuk barang bukti ini.');
        }

        $format = $request->query('size', 'a4');
        $reason = $request->query('reason', 'first_print');

        foreach ($remainingUnits as $ru) {
            $this->labelService->logPrint('remaining', $ru, $format, $reason);
        }

        $remainingUnits->each(function ($unit) {
            $unit->qr_png = $this->qrPngDataUri($unit->qr_content);
        });

        $pdf = Pdf::loadView('labels.remaining-sheet', [
            'remainingUnits' => $remainingUnits,
            'printDate' => now()->format('d M Y H:i'),
        ]);

        // Custom size for Label 121: 165mm x 210mm
        $pdf->setPaper([0, 0, 467.72, 595.28], 'portrait');

        return $pdf->stream("label-sisa-evidence-{$evidenceUnitId}.pdf");
    }

    /**
     * Generate single remaining label PDF.
     * GET /labels/remaining/{id}/single
     */
    public function remainingSingle(Request $request, int $id)
    {
        $remainingUnit = $this->ensureRemainingLabelAccessForRemainingUnit($id);

        if (! $remainingUnit) {
            return back()->with('error', $this->denyRemainingLabelAccessMessage());
        }

        $reason = $request->query('reason', 'first_print');

        $remainingUnit->qr_png = $this->qrPngDataUri($remainingUnit->qr_content);

        $pdf = Pdf::loadView('labels.remaining-single', [
            'remainingUnit' => $remainingUnit,
            'printDate' => now()->format('d M Y H:i'),
        ]);

        // 75mm x 38mm
        $pdf->setPaper([0, 0, 212.60, 107.72], 'landscape');

        return $pdf->stream('label-sisa-'.Str::slug((string) $remainingUnit->remaining_code).'.pdf');
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
                    'qty_remaining' => $remaining->qty_remaining,
                    'uom' => $remaining->uom,
                    'created_at' => optional($remaining->created_at)?->toISOString(),
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
                'message' => 'Gagal menghapus label: '.$e->getMessage(),
            ], 500);
        }
    }
}
