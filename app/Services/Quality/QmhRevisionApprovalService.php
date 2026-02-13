<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QmhRevisionApprovalService
{
    public function approve(QmhDocumentRevision $revision, int $actorId, bool $promoteToNewEdition, ?string $reason): QmhDocumentRevision
    {
        return DB::transaction(function () use ($revision, $actorId, $promoteToNewEdition, $reason) {
            $revision->refresh();

            if ($revision->status !== 'in_approval') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya revisi in_approval yang dapat disahkan.',
                ]);
            }

            if ($revision->disahkan_oleh !== null && $revision->disahkan_oleh !== $actorId) {
                throw new AuthorizationException('Hanya pengesah yang ditugaskan yang dapat melakukan approve.');
            }

            if ($revision->dibuat_oleh === $actorId || $revision->diperiksa_oleh === $actorId) {
                throw ValidationException::withMessages([
                    'actor_id' => 'Pengesah harus berbeda dari pembuat dan pemeriksa.',
                ]);
            }

            if ($promoteToNewEdition && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages([
                    'reason' => 'Alasan wajib diisi untuk manual promote to new edition.',
                ]);
            }

            $document = $revision->document()->lockForUpdate()->first();
            if ($document !== null && $document->doc_type === 'ik') {
                $pairedFrPublishedExists = QmhDocument::query()
                    ->whereIn('doc_type', ['formulir', 'fr'])
                    ->where('paired_ik_id', $document->id)
                    ->where('parent_sop_id', $document->parent_sop_id)
                    ->whereHas('currentRevision', function ($query) {
                        $query->where('status', 'published');
                    })
                    ->exists();

                if (! $pairedFrPublishedExists) {
                    throw ValidationException::withMessages([
                        'paired_fr' => 'IK wajib memiliki minimal satu FR pendamping yang published pada parent SOP yang sama.',
                    ]);
                }
            }

            $latestPublished = QmhDocumentRevision::query()
                ->where('document_id', $revision->document_id)
                ->where('status', 'published')
                ->where('id', '!=', $revision->id)
                ->orderByDesc('edition_number')
                ->orderByDesc('revision_number')
                ->lockForUpdate()
                ->first();

            [$editionNumber, $revisionNumber, $mode] = $this->resolveNextVersion(
                $revision,
                $latestPublished,
                $promoteToNewEdition
            );

            QmhDocumentRevision::query()
                ->where('document_id', $revision->document_id)
                ->where('status', 'published')
                ->where('id', '!=', $revision->id)
                ->update([
                    'status' => 'obsolete',
                    'obsolete_at' => now(),
                ]);

            $revision->edition_number = $editionNumber;
            $revision->revision_number = $revisionNumber;
            $revision->version_label = sprintf('E%d-R%d', $editionNumber, $revisionNumber);
            $revision->status = 'published';
            $revision->version_bump_mode = $mode;
            $revision->disahkan_oleh = $actorId;
            $revision->approved_at = now();
            $revision->effective_date = now()->toDateString();
            $revision->obsolete_at = null;
            $revision->save();

            $revision->document()->update([
                'current_revision_id' => $revision->id,
            ]);

            $this->persistWorkflowEvent($revision->id, $actorId, 'approve', [
                'promote_to_new_edition' => $promoteToNewEdition,
                'reason' => $reason,
            ]);

            $this->persistWorkflowEvent($revision->id, $actorId, 'publish', [
                'edition_number' => $editionNumber,
                'revision_number' => $revisionNumber,
                'version_label' => $revision->version_label,
                'version_bump_mode' => $mode,
                'manual_reason' => $reason,
            ]);

            return $revision->fresh();
        });
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function resolveNextVersion(QmhDocumentRevision $targetRevision, ?QmhDocumentRevision $latestPublished, bool $promoteToNewEdition): array
    {
        if ($latestPublished === null) {
            if ($promoteToNewEdition) {
                return [max(2, $targetRevision->edition_number + 1), 0, 'manual'];
            }

            return [$targetRevision->edition_number, $targetRevision->revision_number, 'auto'];
        }

        if ($promoteToNewEdition) {
            return [$latestPublished->edition_number + 1, 0, 'manual'];
        }

        $nextRevisionNumber = $latestPublished->revision_number + 1;
        if ($nextRevisionNumber >= 10) {
            return [$latestPublished->edition_number + 1, 0, 'auto'];
        }

        return [$latestPublished->edition_number, $nextRevisionNumber, 'auto'];
    }

    private function persistWorkflowEvent(int $revisionId, int $actorId, string $eventType, array $payload): void
    {
        QmhWorkflowEvent::query()->create([
            'revision_id' => $revisionId,
            'event_type' => $eventType,
            'actor_id' => $actorId,
            'payload_json' => $payload,
        ]);
    }
}
