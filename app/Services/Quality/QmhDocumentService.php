<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\QmhWorkflowEvent;
use App\Support\QmhAnswerSanitizer;
use App\Support\QmhFrLayoutProfile;
use App\Support\QmhFrV2Gate;
use App\Support\QmhHtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QmhDocumentService
{
    public function createDraft(array $payload, int $actorId): QmhDocument
    {
        return DB::transaction(function () use ($payload, $actorId) {
            $isFrV2Create = $this->isFrV2CreateMode($payload);
            $template = null;
            if (isset($payload['template_id']) && (int) $payload['template_id'] > 0) {
                $template = QmhTemplate::query()
                    ->whereKey((int) $payload['template_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $templateMetadata = is_array($template?->metadata) ? $template->metadata : [];
            $templateContentHtml = isset($templateMetadata['content_html']) && is_string($templateMetadata['content_html'])
                ? trim($templateMetadata['content_html'])
                : '';

            $payloadContentHtml = isset($payload['content_html']) && is_string($payload['content_html'])
                ? trim($payload['content_html'])
                : '';

            $resolvedContentHtml = $payloadContentHtml !== ''
                ? $payloadContentHtml
                : ($templateContentHtml !== '' ? $templateContentHtml : '<p></p>');

            $resolvedContentHtml = QmhHtmlSanitizer::sanitize($resolvedContentHtml);
            if (trim($resolvedContentHtml) === '') {
                $resolvedContentHtml = '<p></p>';
            }

            $answersJson = null;
            if (array_key_exists('answers_json', $payload)) {
                $answersJson = QmhAnswerSanitizer::sanitizeAnswersJson($payload['answers_json']);
            }

            $schemaSnapshot = null;
            if (array_key_exists('form_schema_json', $payload) && is_array($payload['form_schema_json'])) {
                $schemaSnapshot = $payload['form_schema_json'];
            } elseif ($isFrV2Create) {
                $schemaSnapshot = $this->frV2SchemaSnapshotFromStructureMode($payload['fr_v2_structure_mode'] ?? null);
            } elseif (($payload['doc_type'] ?? null) === 'fr' && is_array($templateMetadata['form_schema'] ?? null)) {
                $schemaSnapshot = $templateMetadata['form_schema'];
            }

            if (($payload['doc_type'] ?? null) === 'fr' && is_array($schemaSnapshot)) {
                $schemaSnapshot = $this->mergeExplicitLayoutMetadata($schemaSnapshot, $templateMetadata);
            }

            $sourcePdfMetadata = $isFrV2Create
                ? $this->persistSourcePdfMetadata($payload['source_pdf_file'] ?? null)
                : [];

            $document = QmhDocument::query()->create([
                'doc_code' => $payload['doc_code'],
                'title' => $payload['title'],
                'clause' => $payload['clause'],
                'doc_type' => $payload['doc_type'] === 'fr' ? 'formulir' : $payload['doc_type'],
                'parent_sop_id' => $payload['parent_sop_id'] ?? null,
                'paired_ik_id' => $payload['paired_ik_id'] ?? null,
                'owner_label' => 'Laboratorium',
                'is_active' => true,
            ]);

            $revision = QmhDocumentRevision::query()->create([
                'document_id' => $document->id,
                'edition_number' => 1,
                'revision_number' => 0,
                'version_label' => 'E1-R0',
                'status' => 'draft',
                'template_id' => $template?->id,
                'template_name' => $template?->name,
                'template_version' => $template?->version,
                'change_summary' => $payload['change_summary'] ?? null,
                'version_bump_mode' => 'auto',
                'editor_json' => $payload['editor_json'] ?? null,
                'answers_json' => $answersJson,
                'form_schema_json' => $schemaSnapshot,
                'effective_date' => null, // Auto-set on publish
                'content_html' => $resolvedContentHtml,
                'content_css' => $payload['content_css'] ?? null,
                'source_pdf_disk' => $sourcePdfMetadata['source_pdf_disk'] ?? null,
                'source_pdf_path' => $sourcePdfMetadata['source_pdf_path'] ?? null,
                'source_pdf_sha256' => $sourcePdfMetadata['source_pdf_sha256'] ?? null,
                'source_pdf_mime' => $sourcePdfMetadata['source_pdf_mime'] ?? null,
                'source_pdf_size' => $sourcePdfMetadata['source_pdf_size'] ?? null,
                'source_pdf_page_count' => $sourcePdfMetadata['source_pdf_page_count'] ?? null,
                'source_pdf_uploaded_at' => $sourcePdfMetadata['source_pdf_uploaded_at'] ?? null,
                'dibuat_oleh' => $payload['dibuat_oleh'] ?? $actorId,
                'diperiksa_oleh' => $payload['diperiksa_oleh'] ?? null,
                'disahkan_oleh' => $payload['disahkan_oleh'] ?? null,
            ]);

            $document->current_revision_id = $revision->id;
            $document->save();

            $this->persistWorkflowEvent($revision->id, $actorId, [
                'doc_code' => $document->doc_code,
                'version_label' => $revision->version_label,
                'template_id' => $template?->id,
                'template_name' => $template?->name,
                'template_version' => $template?->version,
            ]);

            return $document->fresh(['currentRevision']);
        });
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

    protected function persistWorkflowEvent(int $revisionId, int $actorId, array $payload): void
    {
        QmhWorkflowEvent::query()->create([
            'revision_id' => $revisionId,
            'event_type' => 'create_draft',
            'actor_id' => $actorId,
            'payload_json' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $templateMetadata
     * @return array<string, mixed>
     */
    private function mergeExplicitLayoutMetadata(array $schema, array $templateMetadata): array
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

    private function isFrV2CreateMode(array $payload): bool
    {
        return QmhFrV2Gate::isCreateEnabled((string) ($payload['doc_type'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function persistSourcePdfMetadata(mixed $sourcePdfFile): array
    {
        if (! $sourcePdfFile instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'File PDF sumber wajib diunggah untuk mode FR-v2.',
            ]);
        }

        $binary = file_get_contents($sourcePdfFile->getRealPath());
        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'File PDF sumber tidak dapat dibaca.',
            ]);
        }

        if (preg_match('/\/Encrypt\b/i', $binary) === 1) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'File PDF terenkripsi tidak didukung untuk FR-v2.',
            ]);
        }

        $pageCount = $this->estimatePdfPageCount($binary);
        $maxPages = max(1, (int) config('quality.fr_v2.max_pdf_pages', 40));

        if ($pageCount > $maxPages) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'Jumlah halaman PDF melebihi batas maksimum FR-v2.',
            ]);
        }

        $disk = (string) config('quality.fr_v2.source_pdf_disk', 'local');
        $dir = trim((string) config('quality.fr_v2.source_pdf_dir', 'qmh/fr-v2/source-pdf'), '/');
        $filename = sprintf('%s.pdf', Str::uuid()->toString());
        $path = $dir !== '' ? $dir.'/'.$filename : $filename;

        $stored = Storage::disk($disk)->put($path, $binary);
        if (! $stored) {
            throw ValidationException::withMessages([
                'source_pdf_file' => 'Gagal menyimpan file PDF sumber ke storage private.',
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

    private function estimatePdfPageCount(string $binary): int
    {
        $count = preg_match_all('/\/Type\s*\/Page\b/', $binary);

        return max(1, is_int($count) ? $count : 1);
    }
}
