<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use App\Support\QmhAnswerSanitizer;
use App\Support\QmhFrLayoutProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class QmhRevisionDownloadService
{
    private const MAX_LOGO_BYTES = 2_097_152; // 2MB

    /**
     * @var array<int, string>
     */
    private const ALLOWED_LOGO_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    /**
     * @return array{binary: string, filename: string, watermark_text: string}
     */
    public function generateAndLogDownload(
        QmhDocumentRevision $revision,
        int $actorId,
        string $copyType,
        ?string $reason,
        ?string $distributionTarget,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $revision->loadMissing('document');

        if ($copyType === 'controlled' && $revision->status !== 'published') {
            throw ValidationException::withMessages([
                'copy_type' => 'Controlled copy hanya bisa diunduh dari revisi berstatus published.',
            ]);
        }

        if ($copyType === 'uncontrolled' && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan download wajib diisi untuk uncontrolled copy.',
            ]);
        }

        $watermark = $copyType === 'controlled' ? 'SALINAN TERKENDALI' : 'SALINAN TIDAK TERKENDALI';

        $binary = $this->renderPdfBinary($revision, $watermark);

        $fileHash = hash('sha256', $binary);

        DB::transaction(function () use ($revision, $actorId, $copyType, $reason, $distributionTarget, $watermark, $fileHash, $ipAddress, $userAgent) {
            QmhDocumentDownloadLog::query()->create([
                'document_id' => $revision->document_id,
                'revision_id' => $revision->id,
                'edition_number' => $revision->edition_number,
                'revision_number' => $revision->revision_number,
                'copy_type' => $copyType,
                'downloaded_by' => $actorId,
                'downloaded_at' => now(),
                'reason' => $reason,
                'distribution_target' => $distributionTarget,
                'watermark_text' => $watermark,
                'file_hash' => $fileHash,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            QmhWorkflowEvent::query()->create([
                'revision_id' => $revision->id,
                'event_type' => 'download',
                'actor_id' => $actorId,
                'payload_json' => [
                    'copy_type' => $copyType,
                    'reason' => $reason,
                    'watermark_text' => $watermark,
                    'file_hash' => $fileHash,
                ],
            ]);
        });

        $filename = $this->buildDownloadFilename($revision, $copyType);

        return [
            'binary' => $binary,
            'filename' => $filename,
            'watermark_text' => $watermark,
        ];
    }

    public function buildWatermarkedHtml(QmhDocumentRevision $revision, string $watermarkText, ?int $resolvedPageCount = null): string
    {
        $revision->loadMissing(['document.parentSop', 'document.pairedIk', 'template', 'createdBy', 'reviewedBy', 'approvedBy']);

        $renderPayload = $this->buildRenderPayload($revision);

        return view('pdf.qmh-document', [
            'revision' => $revision,
            'schema' => $renderPayload['schema'],
            'answers' => $renderPayload['answers'],
            'watermarkText' => $watermarkText,
            'resolvedPageCount' => $resolvedPageCount,
            'layoutProfile' => $renderPayload['layout_profile'],
            'layoutConfig' => $renderPayload['layout_config'],
            'logoSrc' => $renderPayload['logo_src'],
            'showSignoffFooter' => $renderPayload['show_signoff_footer'],
        ])->render();
    }

    public function renderPdfBinary(QmhDocumentRevision $revision, string $watermarkText, bool $remoteEnabled = false): string
    {
        $renderPayload = $this->buildRenderPayload($revision);

        if ($this->shouldUseSourcePdfPipeline($revision)) {
            $fpdiBinary = $this->renderFrV2SourcePdfBinary($revision, $watermarkText, $renderPayload);
            if ($fpdiBinary !== null) {
                return $fpdiBinary;
            }
        }

        $paperOrientation = $this->resolvePaperOrientation($renderPayload);

        $probeHtml = $this->buildWatermarkedHtml($revision, $watermarkText);

        $probePdf = Pdf::loadHTML($probeHtml)
            ->setPaper('a4', $paperOrientation)
            ->setWarnings(false)
            ->setOption('isRemoteEnabled', $remoteEnabled)
            ->setOption('isHtml5ParserEnabled', true);

        $pageCount = 1;
        try {
            $probePdf->render();

            $dompdf = $probePdf->getDomPDF();
            if (is_object($dompdf) && method_exists($dompdf, 'getCanvas')) {
                $canvas = $dompdf->getCanvas();
                if (is_object($canvas) && method_exists($canvas, 'get_page_count')) {
                    $pageCount = max(1, (int) $canvas->get_page_count());
                }
            }
        } catch (\Throwable) {
            $pageCount = 1;
        }

        $finalHtml = $this->buildWatermarkedHtml($revision, $watermarkText, $pageCount);

        return Pdf::loadHTML($finalHtml)
            ->setPaper('a4', $paperOrientation)
            ->setWarnings(false)
            ->setOption('isRemoteEnabled', $remoteEnabled)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    /**
     * @param  array<string, mixed>  $renderPayload
     */
    private function renderFrV2SourcePdfBinary(QmhDocumentRevision $revision, string $watermarkText, array $renderPayload): ?string
    {
        if (! $this->canUseFpdiSourcePipeline($revision)) {

            return null;
        }

        $revision->loadMissing(['document', 'createdBy', 'reviewedBy', 'approvedBy']);

        /** @var class-string $streamReaderClass */
        $streamReaderClass = '\\setasign\\Fpdi\\PdfParser\\StreamReader';
        /** @var class-string $fpdiClass */
        $fpdiClass = '\\setasign\\Fpdi\\Fpdi';

        try {
            $sourceBinary = $this->loadSourcePdfBinary($revision);
            $reader = $streamReaderClass::createByString($sourceBinary);
            $pdf = new $fpdiClass;
            $pdf->SetAutoPageBreak(false);

            $pageCount = $pdf->setSourceFile($reader);
            $layoutConfig = is_array($renderPayload['layout_config'] ?? null) ? $renderPayload['layout_config'] : [];
            $docCode = (string) ($revision->document?->doc_code ?? '-');
            $docTitle = strtoupper((string) ($revision->document?->title ?? '-'));
            $versionLabel = str_replace('-', '/', (string) ($revision->version_label ?? '-'));
            $statusLabel = strtoupper((string) ($revision->status ?? '-'));
            $effectiveDate = $revision->effective_date ? $revision->effective_date->format('d-m-Y') : '-';

            $drawShell = QmhFrLayoutProfile::shouldRenderFrShellFromPolicy((string) ($layoutConfig['shell_mode'] ?? null));
            $layoutProfile = QmhFrLayoutProfile::normalizeRuntimeProfile((string) ($layoutConfig['layout_profile'] ?? 'legacy'));
            $isDeclarationMinimal = ! $drawShell && in_array($layoutProfile, ['declaration', 'structured_form'], true);
            $showSignoff = (bool) ($renderPayload['show_signoff_footer'] ?? true);
            $targetOrientation = $this->resolvePaperOrientation($renderPayload);
            $signoffPayload = [
                'created_name_rank' => $this->resolveSignerNameRank($revision->createdBy),
                'reviewed_name_rank' => $this->resolveSignerNameRank($revision->reviewedBy),
                'approved_name_rank' => $this->resolveSignerNameRank($revision->approvedBy),
                'created_position' => $this->resolveSignerPosition($revision->createdBy),
                'reviewed_position' => $this->resolveSignerPosition($revision->reviewedBy),
                'approved_position' => $this->resolveSignerPosition($revision->approvedBy),
            ];

            for ($page = 1; $page <= $pageCount; $page++) {
                $sourceTemplate = $pdf->importPage($page);
                $sourceSize = $pdf->getTemplateSize($sourceTemplate);

                $sourceWidth = (float) ($sourceSize['width'] ?? 210.0);
                $sourceHeight = (float) ($sourceSize['height'] ?? 297.0);

                [$targetWidth, $targetHeight] = $this->resolveTargetSizeFromPolicy($sourceWidth, $sourceHeight, $targetOrientation);
                $pageOrientation = $targetWidth >= $targetHeight ? 'L' : 'P';

                $pdf->AddPage($pageOrientation, [$targetWidth, $targetHeight]);

                if ($isDeclarationMinimal) {
                    $topInset = 12.0;
                    $bottomInset = 10.0;
                    $usableHeight = max(40.0, $targetHeight - $topInset - $bottomInset);
                    $scale = min($targetWidth / $sourceWidth, $usableHeight / $sourceHeight);
                    $renderWidth = $sourceWidth * $scale;
                    $renderHeight = $sourceHeight * $scale;
                    $offsetX = ($targetWidth - $renderWidth) / 2;
                    $offsetY = $topInset + (($usableHeight - $renderHeight) / 2);
                } else {
                    $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
                    $renderWidth = $sourceWidth * $scale;
                    $renderHeight = $sourceHeight * $scale;
                    $offsetX = ($targetWidth - $renderWidth) / 2;
                    $offsetY = ($targetHeight - $renderHeight) / 2;
                }

                $this->drawFrV2Watermark($pdf, $targetWidth, $targetHeight, $watermarkText);

                $pdf->useTemplate($sourceTemplate, $offsetX, $offsetY, $renderWidth, $renderHeight, false);

                if ($drawShell) {
                    $this->maskFrV2ShellAreas($pdf, $targetWidth, $targetHeight, $showSignoff);
                }

                if ($drawShell) {
                    $logoAbsolutePath = is_string($renderPayload['logo_path'] ?? null)
                        ? trim((string) $renderPayload['logo_path'])
                        : '';

                    $this->drawFrV2HeaderShell(
                        $pdf,
                        $targetWidth,
                        $docCode,
                        $docTitle,
                        $versionLabel,
                        $effectiveDate,
                        $statusLabel,
                        $logoAbsolutePath !== '' ? $logoAbsolutePath : null
                    );
                }

                if ($isDeclarationMinimal) {
                    $logoAbsolutePath = is_string($renderPayload['logo_path'] ?? null)
                        ? trim((string) $renderPayload['logo_path'])
                        : '';

                    $this->drawFrV2MinimalHeader(
                        $pdf,
                        $targetWidth,
                        $docCode,
                        $docTitle,
                        $versionLabel,
                        $effectiveDate,
                        $statusLabel,
                        $logoAbsolutePath !== '' ? $logoAbsolutePath : null
                    );
                }

                if ($drawShell && $showSignoff) {
                    $this->drawFrV2SignoffFooter($pdf, $targetWidth, $targetHeight, $signoffPayload);
                }

                if ($isDeclarationMinimal) {
                    $this->drawFrV2MinimalFooter($pdf, $targetWidth, $targetHeight, $page, $pageCount);
                } else {
                    $this->drawFrV2FooterMeta($pdf, $targetWidth, $targetHeight, $page, $pageCount);
                }
            }

            return $pdf->Output('S');
        } catch (\Throwable $exception) {
            Log::warning('FPDI pipeline gagal dieksekusi, fallback ke jalur render DomPDF.', [
                'revision_id' => $revision->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function canUseFpdiSourcePipeline(QmhDocumentRevision $revision): bool
    {
        try {
            $fpdiAvailable = class_exists('\\setasign\\Fpdi\\Fpdi');
            $fpdfAvailable = class_exists('\\FPDF');
        } catch (\Throwable $exception) {
            Log::warning('Deteksi runtime FPDI/FPDF gagal, fallback ke jalur render DomPDF.', [
                'revision_id' => $revision->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $fpdiAvailable || ! $fpdfAvailable) {
            Log::warning('Runtime FPDI/FPDF tidak lengkap, fallback ke jalur render DomPDF.', [
                'revision_id' => $revision->id,
                'fpdi_available' => $fpdiAvailable,
                'fpdf_available' => $fpdfAvailable,
            ]);

            return false;
        }

        return true;
    }

    private function shouldUseSourcePdfPipeline(QmhDocumentRevision $revision): bool
    {
        $docType = (string) ($revision->document?->doc_type ?? '');
        if (! in_array($docType, ['formulir', 'fr'], true)) {
            return false;
        }

        return is_string($revision->source_pdf_disk)
            && trim($revision->source_pdf_disk) !== ''
            && is_string($revision->source_pdf_path)
            && trim($revision->source_pdf_path) !== '';
    }

    private function loadSourcePdfBinary(QmhDocumentRevision $revision): string
    {
        $disk = trim((string) $revision->source_pdf_disk);
        $path = trim((string) $revision->source_pdf_path);

        if ($disk === '' || $path === '') {
            throw ValidationException::withMessages([
                'source_pdf' => 'Metadata source PDF FR-v2 tidak lengkap.',
            ]);
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            throw ValidationException::withMessages([
                'source_pdf' => 'File source PDF FR-v2 tidak ditemukan di storage.',
            ]);
        }

        $binary = $storage->get($path);
        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'source_pdf' => 'File source PDF FR-v2 tidak dapat dibaca.',
            ]);
        }

        $expectedSha = strtolower(trim((string) ($revision->source_pdf_sha256 ?? '')));
        if ($expectedSha !== '' && hash('sha256', $binary) !== $expectedSha) {
            throw ValidationException::withMessages([
                'source_pdf' => 'Integritas source PDF FR-v2 tidak valid (checksum mismatch).',
            ]);
        }

        return $binary;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveTargetSizeFromPolicy(float $sourceWidth, float $sourceHeight, string $targetOrientation): array
    {
        if ($targetOrientation === 'landscape') {
            return [max($sourceWidth, $sourceHeight), min($sourceWidth, $sourceHeight)];
        }

        return [min($sourceWidth, $sourceHeight), max($sourceWidth, $sourceHeight)];
    }

    private function drawFrV2HeaderShell(object $pdf, float $pageWidth, string $docCode, string $docTitle, string $versionLabel, string $effectiveDate, string $statusLabel, ?string $logoAbsolutePath = null): void
    {
        $left = 8.0;
        $right = $pageWidth - 8.0;
        $top = 8.0;
        $height = 24.0;

        $pdf->SetDrawColor(17, 24, 39);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($left, $top, $right - $left, $height);

        $leftColWidth = 44.0;
        $rightColWidth = 58.0;
        $middleWidth = ($right - $left) - $leftColWidth - $rightColWidth;

        $pdf->Line($left + $leftColWidth, $top, $left + $leftColWidth, $top + $height);
        $pdf->Line($right - $rightColWidth, $top, $right - $rightColWidth, $top + $height);

        $logoBottomY = $top + 2.0;
        if (is_string($logoAbsolutePath) && $logoAbsolutePath !== '') {
            $logoBottomY = $this->drawFrV2HeaderLogo($pdf, $logoAbsolutePath, $left, $top, $leftColWidth, $logoBottomY);
        }

        $pdf->SetFont('Helvetica', 'B', 6.4);
        $pdf->SetXY($left + 1.5, max($top + 2.5, $logoBottomY + 0.4));
        $pdf->MultiCell($leftColWidth - 3.0, 3.0, "LABORATORIUM PENGUJIAN MUTU\nFARMAPOL PUSDOKKES POLRI", 0, 'C');

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetXY($left + $leftColWidth + 1.5, $top + 4.0);
        $pdf->Cell($middleWidth - 3.0, 4.2, 'FORMULIR', 0, 2, 'C');

        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->SetXY($left + $leftColWidth + 1.5, $top + 9.2);
        $pdf->MultiCell($middleWidth - 3.0, 3.2, mb_substr($docTitle, 0, 80), 0, 'C');

        $metaLeft = $right - $rightColWidth + 1.2;
        $metaValueLeft = $metaLeft + 20.0;
        $metaTop = $top + 2.0;

        $rows = [
            ['No. Dokumen', $docCode],
            ['Edisi/Revisi', $versionLabel],
            ['Tgl. Efektif', $effectiveDate],
            ['Status', $statusLabel],
        ];

        $pdf->SetFont('Helvetica', '', 5.9);
        foreach ($rows as $idx => $row) {
            $y = $metaTop + ($idx * 5.2);
            $pdf->SetXY($metaLeft, $y);
            $pdf->Cell(24.0, 4.2, (string) $row[0], 0, 0, 'L');
            $pdf->SetXY($metaValueLeft, $y);
            $pdf->Cell($rightColWidth - 25.0, 4.2, mb_substr((string) $row[1], 0, 28), 0, 0, 'L');
        }
    }

    private function drawFrV2HeaderLogo(object $pdf, string $logoAbsolutePath, float $left, float $top, float $leftColWidth, float $fallbackY): float
    {
        if (! is_file($logoAbsolutePath) || ! is_readable($logoAbsolutePath)) {
            return $fallbackY;
        }

        $boxWidth = max(8.0, $leftColWidth - 30.0);
        $boxHeight = 8.5;
        $logoWidth = $boxWidth;
        $logoHeight = $boxHeight;

        $imageSize = @getimagesize($logoAbsolutePath);
        if (is_array($imageSize) && isset($imageSize[0], $imageSize[1]) && (int) $imageSize[0] > 0 && (int) $imageSize[1] > 0) {
            $ratio = (float) $imageSize[0] / (float) $imageSize[1];
            if ($ratio > 0.0) {
                $logoWidth = min($boxWidth, $boxHeight * $ratio);
                $logoHeight = min($boxHeight, $boxWidth / $ratio);
            }
        }

        $logoX = $left + (($leftColWidth - $logoWidth) / 2);
        $logoY = $top + 1.6;

        try {
            $pdf->Image($logoAbsolutePath, $logoX, $logoY, $logoWidth, $logoHeight);

            return $logoY + $logoHeight;
        } catch (\Throwable $exception) {
            Log::warning('Gagal merender logo pada shell FPDI FR-v2, lanjut tanpa logo.', [
                'logo_path' => $logoAbsolutePath,
                'error' => $exception->getMessage(),
            ]);

            return $fallbackY;
        }
    }

    private function drawFrV2MinimalHeader(object $pdf, float $pageWidth, string $docCode, string $docTitle, string $versionLabel, string $effectiveDate, string $statusLabel, ?string $logoAbsolutePath = null): void
    {
        $left = 8.0;
        $right = $pageWidth - 8.0;
        $y = 5.6;
        $textLeft = $left;

        if (is_string($logoAbsolutePath) && $logoAbsolutePath !== '' && is_file($logoAbsolutePath) && is_readable($logoAbsolutePath)) {
            try {
                $pdf->Image($logoAbsolutePath, $left, 3.4, 5.8, 5.8);
                $textLeft = $left + 7.2;
            } catch (\Throwable $exception) {
                Log::warning('Gagal merender logo pada header minimal FR-v2.', [
                    'logo_path' => $logoAbsolutePath,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $titlePart = mb_substr(trim($docTitle), 0, 46);
        $statusPart = str_replace('_', ' ', mb_strtoupper(trim($statusLabel)));
        if ($statusPart === '') {
            $statusPart = '-';
        }

        $metaPart = sprintf('%s | %s | %s | %s', $docCode, $versionLabel, $effectiveDate, $statusPart);
        $headerText = trim(sprintf('%s | %s', $titlePart, $metaPart), ' |');

        $fontSize = 7.0;
        $availableWidth = max(40.0, $right - $textLeft);
        $pdf->SetFont('Helvetica', 'B', $fontSize);
        while ($fontSize > 5.2 && $pdf->GetStringWidth($headerText) > $availableWidth) {
            $fontSize -= 0.2;
            $pdf->SetFont('Helvetica', 'B', $fontSize);
        }

        $pdf->SetTextColor(17, 24, 39);
        $pdf->SetXY($textLeft, $y);
        $pdf->Cell($availableWidth, 3.8, $headerText, 0, 0, 'L');

        $pdf->SetDrawColor(17, 24, 39);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($left, 10.8, $right, 10.8);
    }

    private function drawFrV2MinimalFooter(object $pdf, float $pageWidth, float $pageHeight, int $pageNumber, int $pageCount): void
    {
        $left = 8.0;
        $right = $pageWidth - 8.0;
        $lineY = $pageHeight - 7.6;

        $pdf->SetDrawColor(17, 24, 39);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($left, $lineY, $right, $lineY);

        $notice = 'Dokumen internal - reproduksi wajib izin Kepala Farmasi Kepolisian Pusdokkes Polri';
        $pdf->SetFont('Helvetica', '', 6.0);
        $pdf->SetTextColor(127, 29, 29);
        $pdf->SetXY($left, $lineY + 1.1);
        $pdf->Cell($pageWidth - 42.0, 3.2, mb_substr($notice, 0, 100), 0, 0, 'L');

        $pdf->SetTextColor(17, 24, 39);
        $pdf->SetXY($pageWidth - 30.0, $lineY + 1.1);
        $pdf->Cell(22.0, 3.2, sprintf('Halaman %d/%d', $pageNumber, $pageCount), 0, 0, 'R');
    }

    private function maskFrV2ShellAreas(object $pdf, float $pageWidth, float $pageHeight, bool $showSignoff): void
    {
        $topBandHeight = 34.0;
        $bottomBandHeight = $showSignoff ? 42.0 : 12.0;

        $pdf->SetDrawColor(255, 255, 255);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0.0, 0.0, $pageWidth, $topBandHeight, 'F');
        $pdf->Rect(0.0, max(0.0, $pageHeight - $bottomBandHeight), $pageWidth, $bottomBandHeight, 'F');
        $pdf->SetDrawColor(17, 24, 39);
    }

    private function drawFrV2Watermark(object $pdf, float $pageWidth, float $pageHeight, string $watermarkText): void
    {
        $text = trim($watermarkText);
        if ($text === '') {
            return;
        }

        [$r, $g, $b] = $this->resolveFrV2WatermarkColor();

        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetFont('Helvetica', 'B', 22);
        $textWidth = $pdf->GetStringWidth($text);
        $centerX = $pageWidth / 2;
        $centerY = $pageHeight / 2;
        $angle = -30.0;
        $x = $centerX - ($textWidth / 2);
        $y = $centerY - 5.0;

        if (method_exists($pdf, 'Rotate')) {
            $pdf->Rotate($angle, $centerX, $centerY);
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($textWidth, 10.0, $text, 0, 0, 'C');

        if (method_exists($pdf, 'Rotate')) {
            $pdf->Rotate(0);
        }

        $pdf->SetTextColor(17, 24, 39);
    }

    /** @return array{0:int,1:int,2:int} */
    private function resolveFrV2WatermarkColor(): array
    {
        $value = trim((string) env('QMH_FRV2_WATERMARK_RGB', ''));
        if ($value === '') {
            return [208, 215, 224];
        }

        $parts = array_map('trim', explode(',', $value));
        if (count($parts) !== 3) {
            return [208, 215, 224];
        }

        $rgb = array_map(static function (string $part): int {
            $num = (int) $part;

            return max(0, min(255, $num));
        }, $parts);

        return [$rgb[0], $rgb[1], $rgb[2]];
    }

    /**
     * @param  array<string, string>  $signoff
     */
    private function drawFrV2SignoffFooter(object $pdf, float $pageWidth, float $pageHeight, array $signoff): void
    {
        $left = 8.0;
        $tableWidth = $pageWidth - 16.0;
        $top = $pageHeight - 38.0;
        $rowHeight = 4.8;

        $pdf->SetDrawColor(17, 24, 39);
        $pdf->SetLineWidth(0.2);

        $colWidths = [24.0, ($tableWidth - 24.0) / 3, ($tableWidth - 24.0) / 3, ($tableWidth - 24.0) / 3];

        $x = $left;
        foreach ($colWidths as $width) {
            $pdf->Rect($x, $top, $width, $rowHeight);
            $x += $width;
        }

        for ($r = 1; $r <= 3; $r++) {
            $x = $left;
            $y = $top + ($r * $rowHeight);
            foreach ($colWidths as $width) {
                $pdf->Rect($x, $y, $width, $rowHeight);
                $x += $width;
            }
        }

        $pdf->SetFont('Helvetica', 'B', 6.2);
        $pdf->SetXY($left + $colWidths[0], $top + 0.8);
        $pdf->Cell($colWidths[1], 3.2, 'Dibuat Oleh:', 0, 0, 'C');
        $pdf->Cell($colWidths[2], 3.2, 'Diperiksa Oleh:', 0, 0, 'C');
        $pdf->Cell($colWidths[3], 3.2, 'Disahkan Oleh:', 0, 0, 'C');

        $pdf->SetFont('Helvetica', '', 6.0);
        $pdf->SetXY($left + 1.2, $top + $rowHeight + 0.8);
        $pdf->Cell($colWidths[0] - 2.4, 3.0, 'Nama/Pangkat', 0, 0, 'L');
        $pdf->Cell($colWidths[1], 3.0, mb_substr((string) ($signoff['created_name_rank'] ?? '-'), 0, 34), 0, 0, 'L');
        $pdf->Cell($colWidths[2], 3.0, mb_substr((string) ($signoff['reviewed_name_rank'] ?? '-'), 0, 34), 0, 0, 'L');
        $pdf->Cell($colWidths[3], 3.0, mb_substr((string) ($signoff['approved_name_rank'] ?? '-'), 0, 34), 0, 0, 'L');

        $pdf->SetXY($left + 1.2, $top + ($rowHeight * 2) + 0.8);
        $pdf->Cell($colWidths[0] - 2.4, 3.0, 'Tanda Tangan', 0, 0, 'L');

        $pdf->SetXY($left + 1.2, $top + ($rowHeight * 3) + 0.8);
        $pdf->Cell($colWidths[0] - 2.4, 3.0, 'Jabatan', 0, 0, 'L');
        $pdf->Cell($colWidths[1], 3.0, mb_substr((string) ($signoff['created_position'] ?? '-'), 0, 34), 0, 0, 'L');
        $pdf->Cell($colWidths[2], 3.0, mb_substr((string) ($signoff['reviewed_position'] ?? '-'), 0, 34), 0, 0, 'L');
        $pdf->Cell($colWidths[3], 3.0, mb_substr((string) ($signoff['approved_position'] ?? '-'), 0, 34), 0, 0, 'L');
    }

    private function drawFrV2FooterMeta(object $pdf, float $pageWidth, float $pageHeight, int $pageNumber, int $pageCount): void
    {
        $notice = 'Isi Dokumen ini tidak diperkenankan untuk disalin atau digandakan tanpa persetujuan dari Kepala Farmasi Kepolisian Pusdokkes Polri';

        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetTextColor(127, 29, 29);
        $pdf->SetXY(8.0, $pageHeight - 7.0);
        $pdf->Cell($pageWidth - 38.0, 3.6, mb_substr($notice, 0, 140), 0, 0, 'L');

        $pdf->SetTextColor(17, 24, 39);
        $pdf->SetXY($pageWidth - 28.0, $pageHeight - 7.0);
        $pdf->Cell(20.0, 3.6, sprintf('Halaman %d/%d', $pageNumber, $pageCount), 0, 0, 'R');
    }

    private function resolveSignerNameRank(mixed $user): string
    {
        if (! is_object($user)) {
            return '-';
        }

        $name = trim((string) data_get($user, 'name', ''));
        $rank = trim((string) data_get($user, 'rank', ''));

        $namePart = $name !== '' ? $name : '-';
        $rankPart = $rank !== '' ? $rank : '-';

        return sprintf('%s/%s', $namePart, $rankPart);
    }

    private function resolveSignerPosition(mixed $user): string
    {
        if (! is_object($user)) {
            return '-';
        }

        $jabatan = trim((string) data_get($user, 'jabatan', ''));
        if ($jabatan !== '') {
            return $jabatan;
        }

        $role = trim((string) data_get($user, 'role', ''));

        return match ($role) {
            'manajer_teknis' => 'Manajer Teknis',
            'penyelia' => 'Penyelia',
            'analis' => 'Analis',
            'supervisor' => 'Supervisor',
            'admin' => 'Admin',
            default => $role !== '' ? ucwords(str_replace('_', ' ', $role)) : '-',
        };
    }

    /**
     * @return array{schema: array<string, mixed>, answers: array<string, mixed>, layout_profile: string, layout_config: array<string, mixed>, logo_src: string, logo_path: string, show_signoff_footer: bool}
     */
    private function buildRenderPayload(QmhDocumentRevision $revision): array
    {
        $schema = $this->resolveFormSchema($revision);
        $answers = QmhAnswerSanitizer::sanitizeAnswersJson($revision->answers_json);
        $layoutConfig = $this->resolveFrLayoutConfig($revision, $schema);
        $logoAbsolutePath = $this->resolveLogoAbsolutePath($layoutConfig);

        return [
            'schema' => $schema,
            'answers' => $answers,
            'layout_profile' => (string) $layoutConfig['layout_profile'],
            'layout_config' => $layoutConfig,
            'logo_src' => $logoAbsolutePath !== '' ? $this->toDataUri($logoAbsolutePath) : '',
            'logo_path' => $logoAbsolutePath,
            'show_signoff_footer' => (bool) ($layoutConfig['show_signoff_footer'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFormSchema(QmhDocumentRevision $revision): array
    {
        $docType = (string) ($revision->document?->doc_type ?? '');
        $templateMeta = is_array($revision->template?->metadata) ? $revision->template->metadata : [];

        $revisionSchema = $revision->form_schema_json ?? null;
        if (is_array($revisionSchema)) {
            return $revisionSchema;
        }

        $schema = $templateMeta['form_schema'] ?? null;
        if (is_array($schema)) {
            if (in_array($docType, ['formulir', 'fr'], true)) {
                $schema = $this->mergeTemplateLayoutIntoSchema($schema, $templateMeta);
            }

            return $schema;
        }

        $default = $this->defaultFormSchema($docType);
        if (in_array($docType, ['formulir', 'fr'], true)) {
            $default = $this->mergeTemplateLayoutIntoSchema($default, $templateMeta);
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function resolveFrLayoutConfig(QmhDocumentRevision $revision, array $schema): array
    {
        $docType = (string) ($revision->document?->doc_type ?? '');
        if (! in_array($docType, ['formulir', 'fr'], true)) {
            return QmhFrLayoutProfile::defaults();
        }

        $defaults = QmhFrLayoutProfile::defaults();
        $hasSchemaSnapshot = is_array($revision->form_schema_json ?? null);
        $templateMeta = is_array($revision->template?->metadata) ? $revision->template->metadata : [];
        $templateConfig = QmhFrLayoutProfile::fromMetadata($templateMeta);
        $schemaConfig = QmhFrLayoutProfile::fromExplicitMetadata($schema);
        $isSourcePdfRevision = $this->shouldUseSourcePdfPipeline($revision);

        if ($hasSchemaSnapshot) {
            $layoutProfile = array_key_exists('layout_profile', $schema)
                ? QmhFrLayoutProfile::normalizeRuntimeProfile((string) $schema['layout_profile'])
                : 'legacy';
            $policy = QmhFrLayoutProfile::fromMetadata(['layout_profile' => $layoutProfile]);

            return [
                'layout_profile' => $layoutProfile,
                'shell_mode' => (string) ($policy['shell_mode'] ?? $defaults['shell_mode']),
                'orientation_policy' => (string) ($policy['orientation_policy'] ?? $defaults['orientation_policy']),
                'show_signoff_footer' => (bool) ($policy['show_signoff_footer'] ?? $defaults['show_signoff_footer']),
                'logo_source' => $schemaConfig['logo_source'] ?? $defaults['logo_source'],
                'logo_path' => $schemaConfig['logo_path'] ?? $defaults['logo_path'],
                'declaration_header' => $schemaConfig['declaration_header'] ?? $defaults['declaration_header'],
                'risk_matrix_columns' => $schemaConfig['risk_matrix_columns'] ?? $defaults['risk_matrix_columns'],
            ];
        }

        $layoutProfile = array_key_exists('layout_profile', $schema)
            ? QmhFrLayoutProfile::normalizeRuntimeProfile((string) $schema['layout_profile'])
            : QmhFrLayoutProfile::runtimeProfileFromMetadata($templateMeta);
        $policy = QmhFrLayoutProfile::fromMetadata(['layout_profile' => $layoutProfile]);

        return [
            'layout_profile' => $layoutProfile,
            'shell_mode' => (string) ($policy['shell_mode'] ?? $defaults['shell_mode']),
            'orientation_policy' => (string) ($policy['orientation_policy'] ?? $defaults['orientation_policy']),
            'show_signoff_footer' => (bool) ($policy['show_signoff_footer'] ?? $defaults['show_signoff_footer']),
            'logo_source' => $schemaConfig['logo_source'] ?? $templateConfig['logo_source'] ?? $defaults['logo_source'],
            'logo_path' => $schemaConfig['logo_path'] ?? $templateConfig['logo_path'] ?? $defaults['logo_path'],
            'declaration_header' => $schemaConfig['declaration_header'] ?? $templateConfig['declaration_header'] ?? $defaults['declaration_header'],
            'risk_matrix_columns' => $schemaConfig['risk_matrix_columns'] ?? $templateConfig['risk_matrix_columns'] ?? $defaults['risk_matrix_columns'],
        ];
    }

    /**
     * @param  array<string, mixed>  $renderPayload
     */
    private function resolvePaperOrientation(array $renderPayload): string
    {
        $orientationPolicy = strtolower(trim((string) data_get($renderPayload, 'layout_config.orientation_policy', 'portrait')));

        return $orientationPolicy === 'landscape' ? 'landscape' : 'portrait';
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $templateMetadata
     * @return array<string, mixed>
     */
    private function mergeTemplateLayoutIntoSchema(array $schema, array $templateMetadata): array
    {
        $merged = $schema;
        $explicitLayout = QmhFrLayoutProfile::fromExplicitMetadata($templateMetadata);

        foreach (['layout_profile', 'shell_mode', 'orientation_policy', 'show_signoff_footer', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns'] as $key) {
            if (! array_key_exists($key, $explicitLayout)) {
                continue;
            }

            if (array_key_exists($key, $merged)) {
                continue;
            }

            $merged[$key] = $explicitLayout[$key];
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $layoutConfig
     */
    private function resolveLogoAbsolutePath(array $layoutConfig): string
    {
        $defaults = QmhFrLayoutProfile::defaults();
        $source = (string) ($layoutConfig['logo_source'] ?? $defaults['logo_source']);
        $customPath = is_string($layoutConfig['logo_path'] ?? null) ? $layoutConfig['logo_path'] : null;

        $candidates = [];
        $customLogoInvalid = false;
        if ($source === 'custom' && $customPath !== null) {
            $candidates[] = $customPath;
            $candidates[] = settings('pdf.header.logo_path');
            $candidates[] = settings('branding.logo_path');
        } elseif ($source === 'default') {
            $candidates[] = 'images/logo-pusdokkes-polri.png';
        } else {
            $candidates[] = settings('pdf.header.logo_path');
            $candidates[] = settings('branding.logo_path');
            $candidates[] = 'images/logo-pusdokkes-polri.png';
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $absolute = $this->resolveAllowedLogoPath($candidate);
            if ($absolute === null) {
                if ($source === 'custom' && $candidate === $customPath) {
                    $customLogoInvalid = true;
                }

                continue;
            }

            $dataUri = $this->toDataUri($absolute);
            if ($dataUri !== '') {
                if ($customLogoInvalid) {
                    Log::warning('QMH custom logo path tidak valid, fallback ke logo lain.', [
                        'logo_path' => $customPath,
                        'resolved_path' => $absolute,
                    ]);
                }

                return $absolute;
            }
        }

        return '';
    }

    private function resolveAllowedLogoPath(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        $candidates = [];

        if (str_starts_with($trimmed, '/')) {
            $candidates[] = $trimmed;
        }

        $normalized = ltrim($trimmed, '/');
        $candidates[] = public_path($normalized);

        if (str_starts_with($normalized, 'storage/')) {
            $candidates[] = storage_path('app/public/'.substr($normalized, strlen('storage/')));
        }

        $candidates[] = storage_path('app/public/'.$normalized);

        $allowedRoots = [
            realpath(public_path('images')),
            realpath(storage_path('app/public')),
        ];
        $allowedRoots = array_values(array_filter($allowedRoots, static fn ($root): bool => is_string($root) && $root !== ''));

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $real = realpath($candidate);
            if ($real === false || ! is_file($real) || ! is_readable($real)) {
                continue;
            }

            foreach ($allowedRoots as $root) {
                if (str_starts_with($real, $root.DIRECTORY_SEPARATOR) || $real === $root) {
                    return $real;
                }
            }
        }

        return null;
    }

    private function toDataUri(string $absolutePath): string
    {
        $size = @filesize($absolutePath);
        if (! is_int($size) || $size < 1 || $size > self::MAX_LOGO_BYTES) {
            return '';
        }

        $binary = @file_get_contents($absolutePath);
        if (! is_string($binary) || $binary === '') {
            return '';
        }

        $mime = @mime_content_type($absolutePath);
        if (! is_string($mime) || trim($mime) === '') {
            $mime = 'image/png';
        }

        if (! in_array($mime, self::ALLOWED_LOGO_MIME_TYPES, true)) {
            return '';
        }

        return sprintf('data:%s;base64,%s', $mime, base64_encode($binary));
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormSchema(string $docType): array
    {
        if ($docType === 'ik') {
            return [
                'version' => 1,
                'doc_type' => 'ik',
                'questions' => [
                    ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                    ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                    ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                    ['id' => 'reference', 'label' => 'Acuan', 'type' => 'textarea', 'required' => false],
                    ['id' => 'instructions', 'label' => 'Instruksi Kerja', 'type' => 'textarea', 'required' => true],
                    ['id' => 'required_docs', 'label' => 'Dokumentasi Yang Diperlukan', 'type' => 'list', 'required' => false],
                    ['id' => 'closing', 'label' => 'Penutup', 'type' => 'textarea', 'required' => false],
                ],
            ];
        }

        if ($docType !== 'sop') {
            return [
                'version' => 1,
                'doc_type' => $docType,
                'questions' => [],
            ];
        }

        return [
            'version' => 1,
            'doc_type' => 'sop',
            'questions' => [
                ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                ['id' => 'definitions', 'label' => 'Definisi', 'type' => 'list', 'required' => false],
                ['id' => 'references', 'label' => 'Referensi', 'type' => 'list', 'required' => false],
                ['id' => 'procedure', 'label' => 'Prosedur', 'type' => 'textarea', 'required' => true],
                ['id' => 'records', 'label' => 'Rekaman / Form Terkait', 'type' => 'list', 'required' => false],
                ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                ['id' => 'attachments', 'label' => 'Lampiran', 'type' => 'list', 'required' => false],
            ],
        ];
    }

    private function generatePdfBinary(QmhDocumentRevision $revision, string $watermarkText): string
    {
        return $this->renderPdfBinary($revision, $watermarkText);
    }

    private function buildDownloadFilename(QmhDocumentRevision $revision, string $copyType): string
    {
        $revision->loadMissing(['document', 'template']);

        $docCode = $this->normalizeFilenameSegment((string) ($revision->document?->doc_code ?? 'QMH Document'));
        $docTitle = $this->normalizeFilenameSegment((string) ($revision->document?->title ?? 'Dokumen Mutu'));
        $versionLabel = $this->normalizeFilenameSegment((string) ($revision->version_label ?? sprintf('E%d-R%d', (int) $revision->edition_number, (int) $revision->revision_number)));

        $segments = array_values(array_filter([$docCode, $docTitle], static fn (string $segment): bool => $segment !== ''));

        $docType = (string) ($revision->document?->doc_type ?? '');
        if (in_array($docType, ['formulir', 'fr'], true)) {
            $schema = $this->resolveFormSchema($revision);
            $layoutConfig = $this->resolveFrLayoutConfig($revision, $schema);
            $layoutProfile = QmhFrLayoutProfile::normalizeRuntimeProfile((string) ($layoutConfig['layout_profile'] ?? 'legacy'));
            $segments[] = $layoutProfile === 'risk_matrix' ? 'TABEL' : 'NON TABEL';
        }

        if ($versionLabel !== '') {
            $segments[] = $versionLabel;
        }

        $segments[] = $copyType === 'controlled' ? 'TERKENDALI' : 'TIDAK TERKENDALI';

        $filename = trim(implode(' - ', $segments));
        if ($filename === '') {
            $filename = 'QMH Document';
        }

        $filename = mb_substr($filename, 0, 180);

        return $filename.'.pdf';
    }

    private function normalizeFilenameSegment(string $value): string
    {
        $normalized = str_replace('_', ' ', $value);
        $normalized = preg_replace('~[\\/:*?"<>|]+~', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }
}
