<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\QmhWorkflowEvent;
use App\Support\QmhAnswerSanitizer;
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

            $answersJson = null;
            if (array_key_exists('answers_json', $payload)) {
                $answersJson = QmhAnswerSanitizer::sanitizeAnswersJson($payload['answers_json']);
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
                'effective_date' => $payload['effective_date'] ?? null,
                'content_html' => $resolvedContentHtml,
                'content_css' => $payload['content_css'] ?? null,
                'dibuat_oleh' => $actorId,
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
}
