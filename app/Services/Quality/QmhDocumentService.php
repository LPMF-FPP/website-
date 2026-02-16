<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\QmhWorkflowEvent;
use App\Support\QmhAnswerSanitizer;
use App\Support\QmhHtmlSanitizer;
use Illuminate\Support\Facades\DB;

class QmhDocumentService
{
    public function createDraft(array $payload, int $actorId): QmhDocument
    {
        return DB::transaction(function () use ($payload, $actorId) {
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
            } elseif (($payload['doc_type'] ?? null) === 'fr' && is_array($templateMetadata['form_schema'] ?? null)) {
                $schemaSnapshot = $templateMetadata['form_schema'];
            }

            if (($payload['doc_type'] ?? null) === 'fr' && is_array($schemaSnapshot)) {
                $schemaSnapshot = $this->mergeExplicitLayoutMetadata($schemaSnapshot, $templateMetadata);
            }

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
        foreach (['layout_profile', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns'] as $key) {
            if (! array_key_exists($key, $templateMetadata)) {
                continue;
            }

            if (array_key_exists($key, $merged)) {
                continue;
            }

            $merged[$key] = $templateMetadata[$key];
        }

        return $merged;
    }
}
