<?php

namespace App\Http\Controllers;

use App\Models\EvidenceUnit;
use App\Models\RemainingUnit;
use App\Models\TestRequest;
use App\Services\DocumentService;
use App\Services\LabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
     * Build a web response payload for a remaining unit item.
     */
    private function buildRemainingUnitPayload(RemainingUnit $remaining): array
    {
        return [
            'id' => $remaining->id,
            'sample_code' => $remaining->sample_code,
            'remaining_code' => $remaining->remaining_code,
            'qty_remaining' => $remaining->qty_remaining,
            'uom' => $remaining->uom,
            'seal_status_delivered' => $remaining->seal_status_delivered,
            'condition_delivered' => $remaining->condition_delivered,
            'handover_doc_no' => $remaining->handover_doc_no,
            'created_at' => optional($remaining->created_at)?->toISOString(),
            'qr_content' => $remaining->qr_content,
        ];
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
        $this->authorizeLabelRequest($testRequest);

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

        $pdf = $this->buildEvidenceSheetPdf($testRequest, $evidenceUnits);
        $binary = $pdf->output();
        $this->storeLabelDocument(
            $binary,
            $testRequest,
            'label_evidence',
            'Label Barang Bukti '.$testRequest->request_number,
            $request
        );

        return $this->inlinePdfResponse($binary, "label-barang-bukti-{$requestId}.pdf");
    }

    private function buildEvidenceSheetPdf(TestRequest $testRequest, $evidenceUnits)
    {
        $evidencePayloads = $evidenceUnits->map(fn ($unit) => $this->buildLabelPayload($unit))->values();
        $casePayload = $this->buildCaseLabelPayload($testRequest);
        $rows = [];

        for ($i = 0; $i < 4; $i++) {
            $rows[] = [
                'left' => $evidencePayloads->get($i),
                'right' => array_merge($casePayload, ['kind' => 'case']),
            ];
        }

        $pages = collect([
            [
                'layout' => 'mixed',
                'rows' => collect($rows),
            ],
        ]);

        foreach ($evidencePayloads->slice(4)->values()->chunk(8) as $chunk) {
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
            'request' => $testRequest,
            'printDate' => now()->translatedFormat('d M Y H:i'),
        ]);

        $pdf->setPaper([0, 0, 467.72, 595.28], 'portrait');

        return $pdf;
    }

    /**
     * Generate single evidence label PDF.
     * GET /labels/evidence/{id}/single
     */
    public function evidenceSingle(Request $request, int $id)
    {
        $evidenceUnit = EvidenceUnit::with('sample.testRequest')->findOrFail($id);
        $this->authorizeLabelRequest($evidenceUnit->sample?->testRequest);
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

        return $this->inlinePdfResponse($pdf->output(), "label-{$evidenceUnit->sample_code}.pdf");
    }

    private function canAccessRemainingLabelFeature(?string $status): bool
    {
        return in_array($status, [
            'in_testing',
            'ready_for_delivery',
            'completed',
        ], true);
    }

    private function authorizeLabelRequest(?TestRequest $testRequest): void
    {
        if (! $testRequest) {
            abort(404);
        }

        $user = request()->user();
        $isRequestOwner = $user && (int) $testRequest->user_id === (int) $user->id;
        $isAdminRole = $user && in_array($user->role, ['admin', 'admin-lpmf'], true);

        if (! $isRequestOwner && ! $isAdminRole && ! Gate::any(['permintaan.view', 'pengujian.view'])) {
            abort(403);
        }
    }

    private function denyRemainingLabelAccessMessage(): string
    {
        return 'Cetak label sisa tersedia setelah kaji ulang permintaan selesai.';
    }

    private function ensureRemainingLabelAccessForRequest(int $requestId): ?TestRequest
    {
        $testRequest = TestRequest::query()->select(['id', 'status', 'user_id'])->find($requestId);

        $this->authorizeLabelRequest($testRequest);

        if (! $testRequest || ! $this->canAccessRemainingLabelFeature($testRequest->status)) {
            return null;
        }

        return $testRequest;
    }

    private function ensureRemainingLabelAccessForEvidenceUnit(int $evidenceUnitId): ?EvidenceUnit
    {
        $evidenceUnit = EvidenceUnit::query()
            ->select(['id', 'request_id'])
            ->with(['request:id,status,user_id'])
            ->find($evidenceUnitId);

        $this->authorizeLabelRequest($evidenceUnit?->request);

        if (! $evidenceUnit || ! $this->canAccessRemainingLabelFeature($evidenceUnit->request?->status)) {
            return null;
        }

        return $evidenceUnit;
    }

    private function ensureRemainingLabelAccessForRemainingUnit(int $remainingUnitId): ?RemainingUnit
    {
        $remainingUnit = RemainingUnit::query()
            ->with(['evidenceUnit:id,request_id,receipt_code', 'evidenceUnit.request:id,status,user_id'])
            ->find($remainingUnitId);

        $this->authorizeLabelRequest($remainingUnit?->evidenceUnit?->request);

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

        $pdf = $this->buildRemainingSheetPdf($remainingUnits);
        $testRequest = TestRequest::with('investigator')->find($requestId);
        $binary = $pdf->output();
        if ($testRequest) {
            $this->storeLabelDocument(
                $binary,
                $testRequest,
                'label_remaining',
                'Label Sisa Sampel '.$testRequest->request_number,
                $request
            );
        }

        return $this->inlinePdfResponse($binary, "label-sisa-{$requestId}.pdf");
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

        $pdf = $this->buildRemainingSheetPdf($remainingUnits);
        $testRequest = $remainingUnits->first()?->evidenceUnit?->request;
        $binary = $pdf->output();
        if ($testRequest) {
            $this->storeLabelDocument(
                $binary,
                $testRequest,
                'label_remaining',
                'Label Sisa Sampel '.$testRequest->request_number,
                $request
            );
        }

        return $this->inlinePdfResponse($binary, "label-sisa-evidence-{$evidenceUnitId}.pdf");
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

        $testRequest = TestRequest::with('investigator')->find($remainingUnit->evidenceUnit?->request_id);
        $binary = $pdf->output();
        if ($testRequest) {
            $this->labelService->logPrint('remaining', $remainingUnit, 'single', $reason);
            $this->storeLabelDocument(
                $binary,
                $testRequest,
                'remaining_label',
                'Label Sisa Sampel '.$remainingUnit->remaining_code,
                $request
            );
        }

        return $this->inlinePdfResponse($binary, 'label-sisa-'.Str::slug((string) $remainingUnit->remaining_code).'.pdf');
    }

    private function buildRemainingSheetPdf($remainingUnits)
    {
        $remainingUnits = $this->hydrateRemainingUnitsForPdf($remainingUnits);

        $remainingUnits->each(function ($unit) {
            $unit->qr_png = $this->qrPngDataUri($unit->qr_content);
        });

        $pdf = Pdf::loadView('labels.remaining-sheet', [
            'remainingUnits' => $remainingUnits,
            'printDate' => now()->format('d M Y H:i'),
        ]);

        // Custom size for Label 121: 165mm x 210mm
        $pdf->setPaper([0, 0, 467.72, 595.28], 'portrait');

        return $pdf;
    }

    private function hydrateRemainingUnitsForPdf($remainingUnits): Collection
    {
        $remainingUnits = collect($remainingUnits);
        $ids = $remainingUnits->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $sortOrder = $ids->flip();

        return RemainingUnit::query()
            ->with(['evidenceUnit:id,request_id,receipt_code'])
            ->whereIn('id', $ids->all())
            ->get()
            ->sortBy(fn (RemainingUnit $unit) => $sortOrder[$unit->id] ?? PHP_INT_MAX)
            ->values();
    }

    private function syncRemainingLabelDocument(RemainingUnit $remainingUnit, Request $request): ?array
    {
        $remainingUnit->loadMissing('evidenceUnit.request.investigator');
        $testRequest = $remainingUnit->evidenceUnit?->request;

        if (! $testRequest) {
            return null;
        }

        $remainingUnits = $this->labelService->getRemainingUnitsForRequest($testRequest->id);
        if ($remainingUnits->isEmpty()) {
            return null;
        }

        $pdf = $this->buildRemainingSheetPdf($remainingUnits);

        return $this->storeLabelDocument(
            $pdf->output(),
            $testRequest,
            'label_remaining',
            'Label Sisa Sampel '.$testRequest->request_number,
            $request
        );
    }

    private function storeLabelDocument(string $binary, TestRequest $testRequest, string $type, string $baseName, Request $request): ?array
    {
        $testRequest->loadMissing('investigator');
        if (! $testRequest->investigator) {
            return null;
        }

        try {
            $doc = app(DocumentService::class)->storeGenerated(
                binary: $binary,
                ext: 'pdf',
                inv: $testRequest->investigator,
                req: $testRequest,
                type: $type,
                baseName: $baseName,
                replaceExisting: true,
                syncUser: $request->user()
            );

            $googleDriveStatus = data_get($doc->fresh()?->extra, 'google_drive.status');

            return [
                'document_id' => $doc->id,
                'google_drive_status' => $googleDriveStatus,
                'uploaded' => $googleDriveStatus === 'uploaded',
            ];
        } catch (\Throwable $e) {
            Log::warning('Label document generation failed', [
                'request_id' => $testRequest->id,
                'document_type' => $type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function inlinePdfResponse(string $binary, string $filename)
    {
        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
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
            $testRequest = TestRequest::with(['samples', 'investigator'])->findOrFail($validated['request_id']);
            $this->authorizeLabelRequest($testRequest);

            $created = $this->labelService->createEvidenceUnits(
                $validated['request_id'],
                $validated['sample_ids']
            );

            $evidenceUnits = $this->labelService->getEvidenceUnitsForRequest($validated['request_id']);
            $labelDocument = null;

            if ($evidenceUnits->isNotEmpty()) {
                $pdf = $this->buildEvidenceSheetPdf($testRequest, $evidenceUnits);
                $labelDocument = $this->storeLabelDocument(
                    $pdf->output(),
                    $testRequest,
                    'label_evidence',
                    'Label Barang Bukti '.$testRequest->request_number,
                    $request
                );
            }

            $driveMessage = match ($labelDocument['google_drive_status'] ?? null) {
                'uploaded' => ' PDF label tersinkron ke Google Drive.',
                'skipped', 'failed' => ' PDF label tersimpan lokal, tetapi Google Drive belum tersinkronisasi.',
                default => ' PDF label belum dapat disimpan sebagai dokumen.',
            };

            return response()->json([
                'success' => true,
                'message' => $created->count().' label barang bukti berhasil dibuat.'.$driveMessage,
                'drive_status' => $labelDocument['google_drive_status'] ?? 'failed',
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
            $evidenceUnit = $this->ensureRemainingLabelAccessForEvidenceUnit((int) $validated['evidence_unit_id']);

            if (! $evidenceUnit) {
                return response()->json([
                    'success' => false,
                    'message' => $this->denyRemainingLabelAccessMessage(),
                ], 403);
            }

            $remaining = $this->labelService->createRemainingUnit(
                $validated['evidence_unit_id'],
                $validated
            );
            $this->labelService->syncSampleTestingQuantityFromRemainingUnits($evidenceUnit->fresh('sample', 'remainingUnits'));
            $labelDocument = $this->syncRemainingLabelDocument($remaining, $request);

            return response()->json([
                'success' => true,
                'message' => 'Label sisa sampel berhasil dibuat.',
                'drive_status' => $labelDocument['google_drive_status'] ?? 'failed',
                'data' => $this->buildRemainingUnitPayload($remaining),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a remaining unit.
     * PUT /labels/remaining-units/{id}
     */
    public function updateRemainingUnit(Request $request, int $id)
    {
        $remainingUnit = $this->ensureRemainingLabelAccessForRemainingUnit($id);

        if (! $remainingUnit) {
            return response()->json([
                'success' => false,
                'message' => $this->denyRemainingLabelAccessMessage(),
            ], 403);
        }

        $validated = $request->validate([
            'qty_remaining' => ['nullable', 'numeric', 'min:0'],
            'uom' => ['nullable', 'string', 'max:50'],
            'seal_status_delivered' => ['nullable', 'string', 'max:100'],
            'condition_delivered' => ['nullable', 'string', 'max:100'],
            'handover_doc_no' => ['nullable', 'string', 'max:255'],
        ]);

        $remainingUnit->fill($validated);
        $remainingUnit->save();
        $evidenceUnit = $remainingUnit->evidenceUnit()->with('sample', 'remainingUnits')->first();
        if ($evidenceUnit instanceof EvidenceUnit) {
            $this->labelService->syncSampleTestingQuantityFromRemainingUnits($evidenceUnit);
        }
        $remainingUnit->refresh();
        $labelDocument = $this->syncRemainingLabelDocument($remainingUnit, $request);

        return response()->json([
            'success' => true,
            'message' => 'Label sisa sampel berhasil diperbarui.',
            'drive_status' => $labelDocument['google_drive_status'] ?? 'failed',
            'data' => $this->buildRemainingUnitPayload($remainingUnit),
        ]);
    }

    /**
     * Delete a remaining unit.
     * DELETE /labels/remaining-units/{id}
     */
    public function destroyRemainingUnit(int $id)
    {
        try {
            $unit = $this->ensureRemainingLabelAccessForRemainingUnit($id);

            if (! $unit) {
                return response()->json([
                    'success' => false,
                    'message' => $this->denyRemainingLabelAccessMessage(),
                ], 403);
            }

            $evidenceUnit = $unit->evidenceUnit()->with('sample', 'remainingUnits')->first();
            $unit->delete();
            if ($evidenceUnit instanceof EvidenceUnit) {
                $this->labelService->syncSampleTestingQuantityFromRemainingUnits($evidenceUnit->fresh('sample', 'remainingUnits'));
            }

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
