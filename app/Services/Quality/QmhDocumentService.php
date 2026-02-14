<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\QmhWorkflowEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

            $sourceDocx = ['path' => null, 'checksum' => null];
            if ($template !== null) {
                $sourceDocx = $this->cloneTemplateSourceDocx($template, (string) $payload['doc_code']);
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
                'source_docx_path' => $sourceDocx['path'],
                'source_docx_checksum' => $sourceDocx['checksum'],
                'source_docx_version' => 1,
                'change_summary' => $payload['change_summary'] ?? null,
                'version_bump_mode' => 'auto',
                'editor_json' => $payload['editor_json'] ?? null,
                'answers_json' => $payload['answers_json'] ?? null,
                'effective_date' => $payload['effective_date'] ?? null,
                'content_html' => $payload['content_html'] ?? null,
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

    /**
     * @return array{path: string|null, checksum: string|null}
     */
    private function cloneTemplateSourceDocx(QmhTemplate $template, string $docCode): array
    {
        $disk = $template->storage_disk;
        $sourcePath = $template->source_docx_path;

        if ($sourcePath === null || ! Storage::disk($disk)->exists($sourcePath)) {
            return ['path' => null, 'checksum' => null];
        }

        $targetPath = sprintf('qmh/%s/E1-R0/source.docx', $docCode);
        Storage::disk($disk)->copy($sourcePath, $targetPath);

        return [
            'path' => $targetPath,
            'checksum' => hash('sha256', (string) Storage::disk($disk)->get($targetPath)),
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
}
