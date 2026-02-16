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

        $safeDocCode = preg_replace('/[^A-Za-z0-9._-]+/', '_', $revision->document->doc_code ?? 'qmh-document');
        $filename = sprintf('%s-%s-%s.pdf', $safeDocCode, $revision->version_label, $copyType);

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
        ])->render();
    }

    public function renderPdfBinary(QmhDocumentRevision $revision, string $watermarkText, bool $remoteEnabled = false): string
    {
        $probeHtml = $this->buildWatermarkedHtml($revision, $watermarkText);

        $probePdf = Pdf::loadHTML($probeHtml)
            ->setPaper('a4')
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
            ->setPaper('a4')
            ->setWarnings(false)
            ->setOption('isRemoteEnabled', $remoteEnabled)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    /**
     * @return array{schema: array<string, mixed>, answers: array<string, mixed>, layout_profile: string, layout_config: array<string, mixed>, logo_src: string}
     */
    private function buildRenderPayload(QmhDocumentRevision $revision): array
    {
        $schema = $this->resolveFormSchema($revision);
        $answers = QmhAnswerSanitizer::sanitizeAnswersJson($revision->answers_json);
        $layoutConfig = $this->resolveFrLayoutConfig($revision, $schema);

        return [
            'schema' => $schema,
            'answers' => $answers,
            'layout_profile' => (string) $layoutConfig['layout_profile'],
            'layout_config' => $layoutConfig,
            'logo_src' => $this->resolveLogoDataUri($layoutConfig),
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
                $schema = QmhFrLayoutProfile::applyToSchema($schema, $templateMeta);
            }

            return $schema;
        }

        $default = $this->defaultFormSchema($docType);
        if (in_array($docType, ['formulir', 'fr'], true)) {
            $default = QmhFrLayoutProfile::applyToSchema($default, $templateMeta);
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

        $templateMeta = is_array($revision->template?->metadata) ? $revision->template->metadata : [];
        $templateConfig = QmhFrLayoutProfile::fromMetadata($templateMeta);
        $schemaConfig = QmhFrLayoutProfile::fromSchema($schema);

        $layoutProfile = 'legacy';
        if (array_key_exists('layout_profile', $schema)) {
            $layoutProfile = QmhFrLayoutProfile::normalizeRuntimeProfile((string) $schema['layout_profile']);
        } elseif (array_key_exists('layout_profile', $templateMeta)) {
            $layoutProfile = QmhFrLayoutProfile::normalizeRuntimeProfile((string) $templateMeta['layout_profile']);
        }

        return [
            'layout_profile' => $layoutProfile,
            'logo_source' => $schemaConfig['logo_source'] ?? $templateConfig['logo_source'],
            'logo_path' => $schemaConfig['logo_path'] ?? $templateConfig['logo_path'],
            'declaration_header' => $schemaConfig['declaration_header'] ?? $templateConfig['declaration_header'],
            'risk_matrix_columns' => $schemaConfig['risk_matrix_columns'] ?? $templateConfig['risk_matrix_columns'],
        ];
    }

    /**
     * @param  array<string, mixed>  $layoutConfig
     */
    private function resolveLogoDataUri(array $layoutConfig): string
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

                return $dataUri;
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
}
