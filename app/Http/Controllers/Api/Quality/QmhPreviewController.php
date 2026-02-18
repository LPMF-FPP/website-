<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\QmhPreviewPdfRequest;
use App\Http\Requests\Quality\StoreQmhPreviewArtifactRequest;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Services\Quality\QmhRevisionDownloadService;
use App\Support\QmhAnswerSanitizer;
use App\Support\QmhFrLayoutProfile;
use App\Support\QmhFrV2Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QmhPreviewController extends Controller
{
    private const PREVIEW_TOKEN_CACHE_PREFIX = 'qmh:fr_v2:preview_token:';

    public function pdf(QmhPreviewPdfRequest $request, QmhRevisionDownloadService $service): Response
    {
        $validated = $request->validated();

        $docTypeInput = (string) ($validated['doc_type'] ?? '');
        $normalizedTemplateDocType = QmhFrV2Gate::normalizedDocType($docTypeInput);
        $docType = $normalizedTemplateDocType === 'fr' ? 'formulir' : $docTypeInput;
        $isFrV2Preview = QmhFrV2Gate::isCreateEnabled($docTypeInput);
        $templateDocType = $normalizedTemplateDocType;

        $document = new QmhDocument([
            'doc_code' => (string) ($validated['doc_code'] ?? '-'),
            'title' => (string) ($validated['title'] ?? '-'),
            'clause' => (int) ($validated['clause'] ?? 4),
            'doc_type' => $docType,
            'parent_sop_id' => $validated['parent_sop_id'] ?? null,
            'paired_ik_id' => $validated['paired_ik_id'] ?? null,
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        if (! empty($validated['parent_sop_id'])) {
            $parent = QmhDocument::query()
                ->select(['id', 'doc_code', 'title', 'clause', 'doc_type'])
                ->whereKey((int) $validated['parent_sop_id'])
                ->first();

            if ($parent) {
                $document->setRelation('parentSop', $parent);
            }
        }

        if (! empty($validated['paired_ik_id'])) {
            $paired = QmhDocument::query()
                ->select(['id', 'doc_code', 'title', 'clause', 'doc_type', 'parent_sop_id'])
                ->whereKey((int) $validated['paired_ik_id'])
                ->first();

            if ($paired) {
                $document->setRelation('pairedIk', $paired);
            }
        }

        $template = null;
        if (! empty($validated['template_id'])) {
            $template = QmhTemplate::query()
                ->whereKey((int) $validated['template_id'])
                ->where('is_active', true)
                ->where('doc_type', $templateDocType)
                ->first();
        }

        $answers = QmhAnswerSanitizer::sanitizeAnswersJson($validated['answers_json'] ?? []);
        if ($isFrV2Preview) {
            $answers = [];
        }

        $templateMetadata = is_array($template?->metadata) ? $template->metadata : [];
        $schemaSnapshot = null;
        if ($isFrV2Preview) {
            $schemaSnapshot = $this->frV2SchemaSnapshotFromStructureMode($validated['fr_v2_structure_mode'] ?? null);
        } else {
            $schemaSnapshot = is_array($validated['form_schema_json'] ?? null)
                ? $validated['form_schema_json']
                : null;
        }

        if (! $isFrV2Preview && ! is_array($schemaSnapshot) && $template && is_array($templateMetadata['form_schema'] ?? null)) {
            $schemaSnapshot = $templateMetadata['form_schema'];
        }

        if (in_array($docType, ['formulir', 'fr'], true) && is_array($schemaSnapshot)) {
            $normalizedLayoutMetadata = QmhFrLayoutProfile::fromExplicitMetadata($templateMetadata);

            foreach (['layout_profile', 'shell_mode', 'orientation_policy', 'show_signoff_footer', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns'] as $key) {
                if (array_key_exists($key, $schemaSnapshot)) {
                    continue;
                }

                if (! array_key_exists($key, $normalizedLayoutMetadata)) {
                    continue;
                }

                $schemaSnapshot[$key] = $normalizedLayoutMetadata[$key];
            }
        }

        $sourcePdfMetadata = $isFrV2Preview
            ? $this->resolveFrV2PreviewSourceMetadata(
                $validated['source_pdf_token'] ?? null,
                $validated['source_pdf_file'] ?? null
            )
            : [];

        $revision = new QmhDocumentRevision([
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'template_id' => $template?->id,
            'template_name' => $template?->name,
            'template_version' => $template?->version,
            'change_summary' => $validated['change_summary'] ?? null,
            'answers_json' => $answers,
            'form_schema_json' => $schemaSnapshot,
            'effective_date' => null, // Auto-set on publish
            'content_html' => '<p></p>',
            'source_pdf_disk' => $sourcePdfMetadata['source_pdf_disk'] ?? null,
            'source_pdf_path' => $sourcePdfMetadata['source_pdf_path'] ?? null,
            'source_pdf_sha256' => $sourcePdfMetadata['source_pdf_sha256'] ?? null,
            'source_pdf_mime' => $sourcePdfMetadata['source_pdf_mime'] ?? null,
            'source_pdf_size' => $sourcePdfMetadata['source_pdf_size'] ?? null,
            'source_pdf_page_count' => $sourcePdfMetadata['source_pdf_page_count'] ?? null,
            'source_pdf_uploaded_at' => $sourcePdfMetadata['source_pdf_uploaded_at'] ?? null,
        ]);

        $revision->setRelation('document', $document);
        if ($template) {
            $revision->setRelation('template', $template);
        }

        $binary = $service->renderPdfBinary($revision, 'DRAFT PREVIEW', true);

        $safeDocCode = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $document->doc_code);
        $filename = ($safeDocCode ?: 'qmh-preview').'-preview.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function storeArtifact(StoreQmhPreviewArtifactRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $metadata = $this->buildSourcePdfMetadata($validated['source_pdf_file']);
        $token = Str::random(48);
        $ttlMinutes = max(1, (int) config('quality.fr_v2.preview_temp_ttl_minutes', 120));
        $expiresAt = now()->addMinutes($ttlMinutes);

        Cache::put(
            self::PREVIEW_TOKEN_CACHE_PREFIX.$token,
            [
                'disk' => $metadata['source_pdf_disk'] ?? null,
                'path' => $metadata['source_pdf_path'] ?? null,
                'sha256' => $metadata['source_pdf_sha256'] ?? null,
                'mime' => $metadata['source_pdf_mime'] ?? null,
                'size' => $metadata['source_pdf_size'] ?? null,
                'page_count' => $metadata['source_pdf_page_count'] ?? null,
                'uploaded_at' => now()->toISOString(),
            ],
            $expiresAt
        );

        return response()->json([
            'message' => 'Preview artifact FR-v2 siap digunakan.',
            'data' => [
                'source_pdf_token' => $token,
                'expires_at' => $expiresAt->toISOString(),
                'source_pdf_size' => $metadata['source_pdf_size'] ?? null,
                'source_pdf_page_count' => $metadata['source_pdf_page_count'] ?? null,
                'source_pdf_mime' => $metadata['source_pdf_mime'] ?? null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFrV2PreviewSourceMetadata(mixed $sourcePdfToken, mixed $sourcePdfFile): array
    {
        if (is_string($sourcePdfToken) && trim($sourcePdfToken) !== '') {
            return $this->resolveSourcePdfMetadataFromToken($sourcePdfToken);
        }

        return $this->buildSourcePdfMetadata($sourcePdfFile);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSourcePdfMetadataFromToken(string $sourcePdfToken): array
    {
        $payload = Cache::get(self::PREVIEW_TOKEN_CACHE_PREFIX.trim($sourcePdfToken));
        $payload = is_array($payload) ? $payload : [];

        $disk = is_string($payload['disk'] ?? null) ? trim((string) $payload['disk']) : '';
        $path = is_string($payload['path'] ?? null) ? trim((string) $payload['path']) : '';

        if ($disk === '' || $path === '') {
            throw ValidationException::withMessages([
                'source_pdf_token' => 'Token source PDF preview tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages([
                'source_pdf_token' => 'Artefak source PDF preview tidak ditemukan atau sudah dibersihkan.',
            ]);
        }

        return [
            'source_pdf_disk' => $disk,
            'source_pdf_path' => $path,
            'source_pdf_sha256' => is_string($payload['sha256'] ?? null) ? $payload['sha256'] : null,
            'source_pdf_mime' => is_string($payload['mime'] ?? null) ? $payload['mime'] : 'application/pdf',
            'source_pdf_size' => is_int($payload['size'] ?? null) ? (int) $payload['size'] : null,
            'source_pdf_page_count' => is_int($payload['page_count'] ?? null) ? (int) $payload['page_count'] : null,
            'source_pdf_uploaded_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSourcePdfMetadata(mixed $sourcePdfFile): array
    {
        if (! $sourcePdfFile instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'Preview FR-v2 membutuhkan file PDF sumber.',
            ]);
        }

        $binary = file_get_contents($sourcePdfFile->getRealPath());
        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'File PDF sumber preview tidak dapat dibaca.',
            ]);
        }

        if (preg_match('/\/Encrypt\b/i', $binary) === 1) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'File PDF terenkripsi tidak didukung untuk preview FR-v2.',
            ]);
        }

        $maxPages = max(1, (int) config('quality.fr_v2.max_pdf_pages', 40));
        $pageCount = $this->estimatePdfPageCount($binary);
        if ($pageCount > $maxPages) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'Jumlah halaman PDF melebihi batas maksimum preview FR-v2.',
            ]);
        }

        $disk = (string) config('quality.fr_v2.source_pdf_disk', 'local');
        $dir = trim((string) config('quality.fr_v2.preview_temp_dir', 'qmh/fr-v2/preview-temp'), '/');
        $filename = sprintf('%s.pdf', Str::uuid()->toString());
        $path = $dir !== '' ? $dir.'/'.$filename : $filename;
        $stored = Storage::disk($disk)->put($path, $binary);

        if (! $stored) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'Gagal menyimpan artefak source PDF preview.',
            ]);
        }

        $mime = $sourcePdfFile->getMimeType();

        return [
            'source_pdf_disk' => $disk,
            'source_pdf_path' => $path,
            'source_pdf_sha256' => hash('sha256', $binary),
            'source_pdf_mime' => is_string($mime) && $mime !== '' ? $mime : 'application/pdf',
            'source_pdf_size' => strlen($binary),
            'source_pdf_page_count' => $pageCount,
            'source_pdf_uploaded_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function frV2SchemaSnapshotFromStructureMode(mixed $structureMode): array
    {
        $mode = is_string($structureMode) ? strtolower(trim($structureMode)) : '';
        $isTable = $mode === 'table';

        $defaults = QmhFrLayoutProfile::defaults();

        return [
            'version' => 1,
            'doc_type' => 'fr',
            'layout_profile' => $isTable ? 'risk_matrix' : 'structured_form',
            'shell_mode' => 'full',
            'orientation_policy' => $isTable ? 'landscape' : 'portrait',
            'show_signoff_footer' => true,
            'logo_source' => (string) ($defaults['logo_source'] ?? 'settings'),
            'risk_matrix_columns' => $isTable
                ? QmhFrLayoutProfile::normalizeRiskMatrixColumns($defaults['risk_matrix_columns'] ?? null)
                : null,
            'questions' => [],
        ];
    }

    private function estimatePdfPageCount(string $binary): int
    {
        $count = preg_match_all('/\/Type\s*\/Page\b/', $binary);

        return max(1, is_int($count) ? $count : 1);
    }
}
