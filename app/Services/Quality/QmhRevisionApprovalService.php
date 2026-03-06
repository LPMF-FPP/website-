<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use App\Models\StaffTask;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QmhRevisionApprovalService
{
    /**
     * @param  array<string, mixed>|null  $checkerPayload
     */
    public function approve(
        QmhDocumentRevision $revision,
        int $actorId,
        bool $promoteToNewEdition,
        ?string $reason,
        ?string $checkerStatus = null,
        ?array $checkerPayload = null,
        ?string $attestationActor = null,
        ?string $attestationReason = null,
        ?string $incidentRef = null
    ): QmhDocumentRevision {
        return DB::transaction(function () use (
            $revision,
            $actorId,
            $promoteToNewEdition,
            $reason,
            $checkerStatus,
            $checkerPayload,
            $attestationActor,
            $attestationReason,
            $incidentRef
        ) {
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

            $resolvedCheckerStatus = $this->normalizeCheckerStatus($checkerStatus);
            $requiresAttestation = $resolvedCheckerStatus === 'unavailable';

            if ($resolvedCheckerStatus === 'fail') {
                throw ValidationException::withMessages([
                    'checker_status' => 'Approve ditolak karena checker safe-layout mengembalikan status fail.',
                ]);
            }

            if ($requiresAttestation) {
                if ($attestationActor === null || trim($attestationActor) === '') {
                    throw ValidationException::withMessages([
                        'attestation_actor' => 'Attestation actor wajib diisi saat checker unavailable.',
                    ]);
                }

                if ($attestationReason === null || trim($attestationReason) === '') {
                    throw ValidationException::withMessages([
                        'attestation_reason' => 'Attestation reason wajib diisi saat checker unavailable.',
                    ]);
                }

                if ($incidentRef === null || trim($incidentRef) === '') {
                    throw ValidationException::withMessages([
                        'incident_ref' => 'Incident reference wajib diisi saat checker unavailable.',
                    ]);
                }
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

            $nextVersion = $revision->predictNextApprovalVersion($promoteToNewEdition, $latestPublished);
            $editionNumber = $nextVersion['edition_number'];
            $revisionNumber = $nextVersion['revision_number'];
            $mode = $nextVersion['version_bump_mode'];

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
            $revision->version_label = $nextVersion['version_label'];
            $revision->status = 'published';
            $revision->version_bump_mode = $mode;
            $revision->disahkan_oleh = $actorId;
            $revision->approved_at = now();
            $revision->effective_date = now()->toDateString();
            $revision->obsolete_at = null;
            $revision->layout_checker_status = $resolvedCheckerStatus;
            $revision->layout_checker_payload = $checkerPayload;
            $revision->layout_checker_checked_at = now();
            $revision->attestation_actor = $requiresAttestation ? trim((string) $attestationActor) : null;
            $revision->attestation_reason = $requiresAttestation ? trim((string) $attestationReason) : null;
            $revision->attestation_incident_ref = $requiresAttestation ? trim((string) $incidentRef) : null;
            $revision->attestation_recorded_at = $requiresAttestation ? now() : null;
            $revision->save();

            $revision->document()->update([
                'current_revision_id' => $revision->id,
            ]);

            $this->persistWorkflowEvent($revision->id, $actorId, 'approve', [
                'promote_to_new_edition' => $promoteToNewEdition,
                'reason' => $reason,
                'checker_status' => $resolvedCheckerStatus,
                'checker_payload' => $checkerPayload,
            ]);

            if ($resolvedCheckerStatus !== null) {
                $checkerEventType = match ($resolvedCheckerStatus) {
                    'pass' => 'checker_pass',
                    'unavailable' => 'checker_unavailable',
                    default => 'checker_unavailable',
                };

                $this->persistWorkflowEvent($revision->id, $actorId, $checkerEventType, [
                    'checker_status' => $resolvedCheckerStatus,
                    'checker_payload' => $checkerPayload,
                ]);
            }

            if ($requiresAttestation) {
                $this->persistWorkflowEvent($revision->id, $actorId, 'attestation_fallback', [
                    'attestation_actor' => trim((string) $attestationActor),
                    'attestation_reason' => trim((string) $attestationReason),
                    'incident_ref' => trim((string) $incidentRef),
                ]);
            }

            $this->persistWorkflowEvent($revision->id, $actorId, 'publish', [
                'edition_number' => $editionNumber,
                'revision_number' => $revisionNumber,
                'version_label' => $revision->version_label,
                'version_bump_mode' => $mode,
                'manual_reason' => $reason,
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

    private function normalizeCheckerStatus(?string $checkerStatus): ?string
    {
        $raw = strtolower(trim((string) $checkerStatus));
        if ($raw === '') {
            return null;
        }

        return match ($raw) {
            'timeout' => 'unavailable',
            'pass', 'fail', 'unavailable' => $raw,
            default => null,
        };
    }
}
