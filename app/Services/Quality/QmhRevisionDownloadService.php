<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Process\Process;

class QmhRevisionDownloadService
{
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

        $watermark = $copyType === 'controlled' ? 'CONTROLLED COPY' : 'UNCONTROLLED COPY';

        $binary = $this->generatePdfBinary($revision, $watermark);

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

        $safeDocCode = preg_replace('/[^A-Za-z0-9._-]+/', '_', $revision->document->doc_code ?? 'qmh-document');
        $filename = sprintf('%s-%s-%s.pdf', $safeDocCode, $revision->version_label, $copyType);

        return [
            'binary' => $binary,
            'filename' => $filename,
            'watermark_text' => $watermark,
        ];
    }

    public function buildWatermarkedHtml(QmhDocumentRevision $revision, string $watermarkText): string
    {
        $revision->loadMissing(['document', 'template', 'createdBy', 'reviewedBy', 'approvedBy']);

        $schema = $this->resolveFormSchema($revision);
        $answers = is_array($revision->answers_json) ? $revision->answers_json : [];

        return view('pdf.qmh-document', [
            'revision' => $revision,
            'schema' => $schema,
            'answers' => $answers,
            'watermarkText' => $watermarkText,
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFormSchema(QmhDocumentRevision $revision): array
    {
        $docType = (string) ($revision->document?->doc_type ?? '');
        $templateMeta = is_array($revision->template?->metadata) ? $revision->template->metadata : [];

        $schema = $templateMeta['form_schema'] ?? null;
        if (is_array($schema)) {
            return $schema;
        }

        return $this->defaultFormSchema($docType);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormSchema(string $docType): array
    {
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
        if ($this->shouldUseDocxToPdf($revision)) {
            return $this->generateStampedPdfFromDocx($revision, $watermarkText);
        }

        $html = $this->buildWatermarkedHtml($revision, $watermarkText);

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setWarnings(false)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    private function shouldUseDocxToPdf(QmhDocumentRevision $revision): bool
    {
        if (! (bool) config('quality.export.docx_to_pdf.enabled', false)) {
            return false;
        }

        if (! (bool) $revision->export_pdf_from_docx) {
            return false;
        }

        $path = $revision->source_docx_path;
        if ($path === null || trim($path) === '') {
            return false;
        }

        $revision->loadMissing('template');
        $disk = $revision->template?->storage_disk ?? 'local';

        return Storage::disk($disk)->exists($path);
    }

    private function generateStampedPdfFromDocx(QmhDocumentRevision $revision, string $watermarkText): string
    {
        $soffice = (string) config('quality.export.docx_to_pdf.soffice_binary', 'soffice');
        $qpdf = (string) config('quality.export.docx_to_pdf.qpdf_binary', 'qpdf');

        $this->assertBinaryAvailable($soffice);
        $this->assertBinaryAvailable($qpdf);

        $revision->loadMissing('template');
        $disk = $revision->template?->storage_disk ?? 'local';
        $docxPath = (string) $revision->source_docx_path;

        $docxBinary = (string) Storage::disk($disk)->get($docxPath);

        $tmpRoot = storage_path('app/tmp/qmh');
        if (! is_dir($tmpRoot)) {
            mkdir($tmpRoot, 0755, true);
        }

        $jobId = (string) Str::uuid();
        $inputDocx = $tmpRoot."/{$jobId}.docx";
        $outputDir = $tmpRoot."/{$jobId}-out";
        $profileDir = $tmpRoot."/{$jobId}-profile";
        $inputPdf = $tmpRoot."/{$jobId}.pdf";
        $overlayPdf = $tmpRoot."/{$jobId}-overlay.pdf";
        $outputPdf = $tmpRoot."/{$jobId}-watermarked.pdf";

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }

        file_put_contents($inputDocx, $docxBinary);

        $timeout = max(10, (int) config('quality.export.docx_to_pdf.timeout_seconds', 90));
        $process = new Process([
            $soffice,
            '--headless',
            '--nologo',
            '--nolockcheck',
            '--nodefault',
            '--nofirststartwizard',
            '--invisible',
            '-env:UserInstallation=file://'.$profileDir,
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDir,
            $inputDocx,
        ], null, [
            // LibreOffice headless often needs a writable HOME for profile/tmp.
            'HOME' => $tmpRoot,
        ]);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('QMH DOCX->PDF conversion failed', [
                'revision_id' => $revision->id,
                'docx_path' => $docxPath,
                'exit_code' => $process->getExitCode(),
                'error_output' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);

            $this->cleanupTempFiles([$inputDocx, $inputPdf, $overlayPdf, $outputPdf], [$outputDir, $profileDir]);
            throw new ServiceUnavailableHttpException(null, 'Gagal mengonversi DOCX ke PDF. Hubungi administrator.');
        }

        $generated = $this->findFirstPdfInDir($outputDir);
        if ($generated === null) {
            $this->cleanupTempFiles([$inputDocx, $inputPdf, $overlayPdf, $outputPdf], [$outputDir, $profileDir]);
            throw new ServiceUnavailableHttpException(null, 'Gagal mengonversi DOCX ke PDF. Hubungi administrator.');
        }

        rename($generated, $inputPdf);
        file_put_contents($overlayPdf, $this->buildWatermarkOverlayPdf($watermarkText));

        $stamp = new Process([
            $qpdf,
            $inputPdf,
            '--overlay',
            $overlayPdf,
            '--repeat=1',
            '--',
            $outputPdf,
        ]);
        $stamp->setTimeout(30);
        $stamp->run();

        if (! $stamp->isSuccessful() || ! file_exists($outputPdf)) {
            Log::error('QMH PDF watermark stamping failed', [
                'revision_id' => $revision->id,
                'exit_code' => $stamp->getExitCode(),
                'error_output' => $stamp->getErrorOutput(),
                'output' => $stamp->getOutput(),
            ]);

            $this->cleanupTempFiles([$inputDocx, $inputPdf, $overlayPdf, $outputPdf], [$outputDir, $profileDir]);
            throw new ServiceUnavailableHttpException(null, 'Gagal menerapkan watermark PDF. Hubungi administrator.');
        }

        $binary = (string) file_get_contents($outputPdf);
        $this->cleanupTempFiles([$inputDocx, $inputPdf, $overlayPdf, $outputPdf], [$outputDir, $profileDir]);

        return $binary;
    }

    private function buildWatermarkOverlayPdf(string $watermarkText): string
    {
        $html = view('quality.pdf.watermark-overlay', [
            'watermarkText' => $watermarkText,
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setWarnings(false)
            ->output();
    }

    private function assertBinaryAvailable(string $binary): void
    {
        if ($this->binaryExists($binary)) {
            return;
        }

        throw new ServiceUnavailableHttpException(null, 'Konversi DOCX ke PDF belum tersedia. Hubungi administrator.');
    }

    private function binaryExists(string $binary): bool
    {
        $candidate = trim($binary);
        if ($candidate === '') {
            return false;
        }

        if (str_contains($candidate, '/')) {
            return is_file($candidate) && is_executable($candidate);
        }

        $process = new Process(['sh', '-lc', 'command -v '.escapeshellarg($candidate).' >/dev/null 2>&1']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }

    private function findFirstPdfInDir(string $dir): ?string
    {
        $files = glob(rtrim($dir, '/').'/*.pdf');
        if (! is_array($files) || $files === []) {
            return null;
        }

        return $files[0];
    }

    /**
     * @param  array<int, string>  $files
     * @param  array<int, string>  $dirs
     */
    private function cleanupTempFiles(array $files, array $dirs): void
    {
        foreach ($files as $path) {
            if (is_string($path) && $path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }

        foreach ($dirs as $dir) {
            if (! is_string($dir) || $dir === '' || ! is_dir($dir)) {
                continue;
            }

            $this->deleteDirectoryRecursive($dir);
        }
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        $target = rtrim($dir, '/');
        if ($target === '' || ! is_dir($target)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $path) {
            $pathname = $path->getPathname();

            if ($path->isDir()) {
                @rmdir($pathname);

                continue;
            }

            @unlink($pathname);
        }

        @rmdir($target);
    }
}
