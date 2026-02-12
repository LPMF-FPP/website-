<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use Illuminate\Support\Facades\DB;

class QmhDocumentService
{
    public function createDraft(array $payload, int $actorId): QmhDocument
    {
        return DB::transaction(function () use ($payload, $actorId) {
            $document = QmhDocument::query()->create([
                'doc_code' => $payload['doc_code'],
                'title' => $payload['title'],
                'clause' => $payload['clause'],
                'doc_type' => $payload['doc_type'],
                'owner_label' => 'Laboratorium',
                'is_active' => true,
            ]);

            $revision = QmhDocumentRevision::query()->create([
                'document_id' => $document->id,
                'edition_number' => 1,
                'revision_number' => 0,
                'version_label' => 'E1-R0',
                'status' => 'draft',
                'change_summary' => $payload['change_summary'] ?? null,
                'version_bump_mode' => 'auto',
                'editor_json' => $payload['editor_json'] ?? null,
                'content_html' => $payload['content_html'] ?? null,
                'content_css' => $payload['content_css'] ?? null,
                'dibuat_oleh' => $actorId,
            ]);

            $document->current_revision_id = $revision->id;
            $document->save();

            $this->persistWorkflowEvent($revision->id, $actorId, [
                'doc_code' => $document->doc_code,
                'version_label' => $revision->version_label,
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
