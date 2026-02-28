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
use Normalizer;

class QmhPreviewController extends Controller
{
    private const PREVIEW_TOKEN_CACHE_PREFIX = 'qmh:fr_v2:preview_token:';

    private const MAX_CANONICAL_NUMERIC_EXPONENT = 1000;

    private const MAX_CANONICAL_NUMERIC_PAD = 5000;

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
                ->whereNull('archived_at')
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

        $schemaCanonicalHash = null;
        if (is_array($schemaSnapshot)) {
            if (! class_exists(Normalizer::class)) {
                throw ValidationException::withMessages([
                    'form_schema_json' => 'Canonical hash membutuhkan ekstensi intl (Normalizer) yang aktif.',
                ]);
            }

            $canonicalJson = json_encode(
                $this->canonicalizeSchemaForHash($schemaSnapshot),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if (is_string($canonicalJson) && $canonicalJson !== '') {
                $schemaCanonicalHash = hash('sha256', $canonicalJson);
            }
        }

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

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        $includeSchemaHashHeader = $request->boolean('include_schema_hash')
            || strtolower((string) $request->header('X-QMH-Include-Schema-Hash', '')) === '1';

        if ($includeSchemaHashHeader && is_string($schemaCanonicalHash) && $schemaCanonicalHash !== '') {
            $headers['X-QMH-Schema-Canonical-Hash'] = $schemaCanonicalHash;
        }

        return response($binary, 200, $headers);
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
            'layout_profile' => $isTable ? 'risk_matrix' : 'non_table',
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

    private function canonicalizeSchemaForHash(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->canonicalizeSchemaForHash($item), $value);
            }

            $normalized = [];
            $keys = array_keys($value);
            sort($keys, SORT_STRING);

            foreach ($keys as $key) {
                $normalized[(string) $key] = $this->canonicalizeSchemaForHash($value[$key]);
            }

            return $normalized;
        }

        if (is_string($value)) {
            return $this->normalizeStringForHash($value);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $this->normalizeFloatForHash($value);
        }

        return $value;
    }

    private function normalizeStringForHash(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        $normalized = Normalizer::normalize($normalized, Normalizer::FORM_C) ?: $normalized;

        return $normalized;
    }

    private function normalizeNumericStringForHash(string $numeric): string
    {
        $n = strtolower(trim($numeric));
        $sign = '';
        if (str_starts_with($n, '+')) {
            $n = substr($n, 1);
        } elseif (str_starts_with($n, '-')) {
            $sign = '-';
            $n = substr($n, 1);
        }

        $exp = 0;
        $mantissaForFallback = $n;
        if (str_contains($n, 'e')) {
            [$mantissa, $exponent] = explode('e', $n, 2);
            $n = $mantissa;
            $exp = (int) $exponent;
            $mantissaForFallback = $mantissa;
        }

        if (abs($exp) > self::MAX_CANONICAL_NUMERIC_EXPONENT) {
            return $this->normalizeScientificFallbackForHash($sign, $mantissaForFallback, $exp);
        }

        $parts = explode('.', $n, 2);
        $intPart = ltrim($parts[0] !== '' ? $parts[0] : '0', '0');
        $fracPart = $parts[1] ?? '';

        $digits = ($intPart === '' ? '0' : $intPart).$fracPart;
        $decimalPos = ($intPart === '' ? 1 : strlen($intPart)) + $exp;

        if ($digits === '' || preg_match('/^0+$/', $digits) === 1) {
            return '0';
        }

        if ($decimalPos <= 0) {
            $padLength = abs($decimalPos);
            if ($padLength > self::MAX_CANONICAL_NUMERIC_PAD) {
                return $this->normalizeScientificFallbackForHash($sign, $mantissaForFallback, $exp);
            }

            $digits = str_repeat('0', abs($decimalPos)).$digits;
            $decimalPos = 0;
        }

        if ($decimalPos >= strlen($digits)) {
            $padLength = $decimalPos - strlen($digits);
            if ($padLength > self::MAX_CANONICAL_NUMERIC_PAD) {
                return $this->normalizeScientificFallbackForHash($sign, $mantissaForFallback, $exp);
            }

            $result = $digits.str_repeat('0', $decimalPos - strlen($digits));
        } else {
            $result = substr($digits, 0, $decimalPos).'.'.substr($digits, $decimalPos);
        }

        if (str_contains($result, '.')) {
            $result = rtrim(rtrim($result, '0'), '.');
        }

        $result = ltrim($result, '0');
        if ($result === '' || str_starts_with($result, '.')) {
            $result = '0'.$result;
        }

        if ($result === '0') {
            return '0';
        }

        return $sign === '-' ? '-'.$result : $result;
    }

    private function normalizeScientificFallbackForHash(string $sign, string $mantissa, int $exp): string
    {
        $normalizedMantissa = trim(strtolower($mantissa));
        if ($normalizedMantissa === '') {
            return '0';
        }

        $parts = explode('.', $normalizedMantissa, 2);
        $intPart = ltrim($parts[0] !== '' ? $parts[0] : '0', '0');
        $fracPart = rtrim($parts[1] ?? '', '0');

        if (($intPart === '' || preg_match('/^0+$/', $intPart) === 1) && ($fracPart === '' || preg_match('/^0+$/', $fracPart) === 1)) {
            return '0';
        }

        $intPart = $intPart === '' ? '0' : $intPart;
        $mantissaCanonical = $fracPart === '' ? $intPart : $intPart.'.'.$fracPart;

        if ($exp !== 0) {
            $mantissaCanonical .= 'e'.$exp;
        }

        return $sign === '-' ? '-'.$mantissaCanonical : $mantissaCanonical;
    }

    private function normalizeFloatForHash(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);
        if (! is_string($encoded) || $encoded === '') {
            $fallback = (string) $value;

            return $fallback === '-0' ? '0' : $fallback;
        }

        $normalized = strtolower($encoded);
        if (str_contains($normalized, 'e')) {
            return $this->normalizeNumericStringForHash($normalized);
        }

        if (str_contains($normalized, '.')) {
            $normalized = rtrim(rtrim($normalized, '0'), '.');
        }

        if ($normalized === '-0') {
            return '0';
        }

        return $normalized;
    }
}
