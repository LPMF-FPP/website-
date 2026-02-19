<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use App\Models\StaffTask;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QmhRevisionRejectionService
{
    public function rejectToDraft(QmhDocumentRevision $revision, int $actorId, string $reason): QmhDocumentRevision
    {
        return DB::transaction(function () use ($revision, $actorId, $reason): QmhDocumentRevision {
            $revision->refresh();

            if ($revision->status !== 'in_approval') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya revisi in_approval yang dapat ditolak kembali ke draft.',
                ]);
            }

            if ($revision->disahkan_oleh !== null && $revision->disahkan_oleh !== $actorId) {
                throw new AuthorizationException('Hanya pengesah yang ditugaskan yang dapat menolak revisi ini.');
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Alasan penolakan wajib diisi.',
                ]);
            }

            $revision->status = 'draft';
            $revision->reviewed_at = now();
            $revision->approved_at = null;
            $revision->effective_date = null;
            $revision->save();

            $this->persistWorkflowEvent($revision->id, $actorId, 'reject', [
                'reason' => $reason,
                'from_status' => 'in_approval',
                'to_status' => 'draft',
            ]);

            StaffTask::query()
                ->forQmhRevision($revision->id)
                ->where('workflow_stage', StaffTask::WORKFLOW_STAGE_APPROVAL)
                ->whereIn('status', [StaffTask::STATUS_PENDING, StaffTask::STATUS_IN_PROGRESS])
                ->update([
                    'status' => StaffTask::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'token_consumed_at' => now(),
                ]);

            return $revision->fresh();
        });
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
