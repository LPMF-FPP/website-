<?php

namespace App\Services\Quality;

use App\Jobs\SendQmhWorkflowTaskNotificationJob;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\QmhWorkflowEvent;
use App\Models\StaffTask;
use App\Models\User;
use App\Support\QmhFormAnswersValidator;
use App\Support\QmhFrLayoutProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QmhRevisionTransitionService
{
    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    public function closeLegacyAndDuplicateToV2(
        QmhDocumentRevision $revision,
        int $actorId,
        string $idempotencyKey,
        string $reason,
        ?array $context = null
    ): array {
        return DB::transaction(function () use ($revision, $actorId, $idempotencyKey, $reason, $context): array {
            $scope = sprintf('close_legacy_and_duplicate_to_v2:revision:%d', (int) $revision->id);
            $requestHash = hash('sha256', json_encode([
                'revision_id' => (int) $revision->id,
                'reason' => trim($reason),
                'context' => $context ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $idempotencyRecord = DB::table('qmh_workflow_idempotency_keys')
                ->where('scope', $scope)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($idempotencyRecord !== null) {
                if (is_string($idempotencyRecord->request_hash) && $idempotencyRecord->request_hash !== '' && $idempotencyRecord->request_hash !== $requestHash) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Idempotency key sudah digunakan untuk payload yang berbeda.',
                    ]);
                }

                $payload = is_string($idempotencyRecord->payload_json)
                    ? json_decode($idempotencyRecord->payload_json, true)
                    : null;
                $payload = is_array($payload) ? $payload : [];

                $this->persistWorkflowEvent((int) $revision->id, $actorId, 'cutover_idempotent_replay', [
                    'idempotency_key' => $idempotencyKey,
                    'result_ref' => $idempotencyRecord->result_ref,
                ]);

                return [
                    'idempotent_replay' => true,
                    'legacy_revision_id' => (int) $revision->id,
                    'new_document_id' => isset($payload['new_document_id']) ? (int) $payload['new_document_id'] : null,
                    'new_revision_id' => isset($payload['new_revision_id']) ? (int) $payload['new_revision_id'] : null,
                ];
            }

            $legacyRevision = QmhDocumentRevision::query()
                ->whereKey($revision->id)
                ->lockForUpdate()
                ->firstOrFail();

            $legacyRevision->loadMissing('document');

            $legacyDocument = $legacyRevision->document;
            if ($legacyDocument === null) {
                throw ValidationException::withMessages([
                    'revision' => 'Dokumen legacy tidak ditemukan.',
                ]);
            }

            if (! in_array((string) $legacyDocument->doc_type, ['formulir', 'fr'], true)) {
                throw ValidationException::withMessages([
                    'doc_type' => 'Cutover legacy ke FR-v2 hanya berlaku untuk dokumen Formulir.',
                ]);
            }

            if (! in_array((string) $legacyRevision->status, ['published', 'obsolete', 'closed_legacy'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya revisi formulir published/obsolete yang dapat dicutover.',
                ]);
            }

            $legacyRevision->status = 'closed_legacy';
            $legacyRevision->obsolete_at = now();
            $legacyRevision->save();

            $newDocCode = $this->generateDuplicateDocCode((string) $legacyDocument->doc_code);
            $newDocument = $legacyDocument->replicate([
                'doc_code',
                'current_revision_id',
                'created_at',
                'updated_at',
            ]);
            $newDocument->doc_code = $newDocCode;
            $newDocument->title = trim((string) $legacyDocument->title).' (FR-v2)';
            $newDocument->doc_type = 'formulir';
            $newDocument->is_active = true;
            $newDocument->current_revision_id = null;
            $newDocument->save();

            $newRevision = $legacyRevision->replicate([
                'document_id',
                'edition_number',
                'revision_number',
                'version_label',
                'status',
                'created_at',
                'updated_at',
                'submitted_at',
                'reviewed_at',
                'approved_at',
                'effective_date',
                'obsolete_at',
                'layout_checker_status',
                'layout_checker_payload',
                'layout_checker_checked_at',
                'attestation_actor',
                'attestation_reason',
                'attestation_incident_ref',
                'attestation_recorded_at',
            ]);

            $newRevision->document_id = $newDocument->id;
            $newRevision->edition_number = 1;
            $newRevision->revision_number = 0;
            $newRevision->version_label = 'E1-R0';
            $newRevision->status = 'draft';
            $newRevision->version_bump_mode = 'manual';
            $newRevision->change_summary = trim($reason);
            $newRevision->submitted_at = null;
            $newRevision->reviewed_at = null;
            $newRevision->approved_at = null;
            $newRevision->effective_date = null;
            $newRevision->obsolete_at = null;
            $newRevision->save();

            $newDocument->current_revision_id = $newRevision->id;
            $newDocument->save();

            $this->persistWorkflowEvent((int) $legacyRevision->id, $actorId, 'close_legacy', [
                'reason' => trim($reason),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->persistWorkflowEvent((int) $legacyRevision->id, $actorId, 'duplicate_to_v2', [
                'new_document_id' => (int) $newDocument->id,
                'new_revision_id' => (int) $newRevision->id,
                'idempotency_key' => $idempotencyKey,
                'context' => $context,
            ]);

            DB::table('qmh_workflow_idempotency_keys')->insert([
                'scope' => $scope,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'result_ref' => sprintf('revision:%d', (int) $newRevision->id),
                'payload_json' => json_encode([
                    'new_document_id' => (int) $newDocument->id,
                    'new_revision_id' => (int) $newRevision->id,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'idempotent_replay' => false,
                'legacy_revision_id' => (int) $legacyRevision->id,
                'new_document_id' => (int) $newDocument->id,
                'new_revision_id' => (int) $newRevision->id,
            ];
        });
    }

    public function submitForReview(QmhDocumentRevision $revision, int $actorId, int $reviewerId): QmhDocumentRevision
    {
        return DB::transaction(function () use ($revision, $actorId, $reviewerId) {
            $revision->refresh();

            $revision->loadMissing('document');

            if ($revision->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya revisi draft yang dapat dikirim ke review.',
                ]);
            }

            if ($revision->dibuat_oleh === $reviewerId) {
                throw ValidationException::withMessages([
                    'reviewer_id' => 'Pemeriksa tidak boleh sama dengan pembuat.',
                ]);
            }

            $lock = $revision->lock()->lockForUpdate()->first();
            if ($lock === null || ! $lock->isActive() || $lock->locked_by !== $actorId) {
                throw new AuthorizationException('Submit review hanya bisa dilakukan oleh pemegang lock aktif.');
            }

            if (($revision->document?->doc_type ?? '') === 'formulir') {
                $schema = is_array($revision->form_schema_json ?? null) ? $revision->form_schema_json : null;
                $hasSchemaSnapshot = is_array($schema);

                $template = null;
                if ((int) ($revision->template_id ?? 0) > 0) {
                    $template = QmhTemplate::query()->find((int) $revision->template_id);
                }

                $metadata = is_array($template?->metadata) ? $template->metadata : [];
                if (! is_array($schema)) {
                    $schema = $metadata['form_schema'] ?? null;
                }

                if (is_array($schema)) {
                    if (! $hasSchemaSnapshot) {
                        $schema = $this->mergeMissingLayoutConfig($schema, $metadata);
                    }

                    $result = QmhFormAnswersValidator::validateAndNormalize(
                        $schema,
                        $revision->answers_json ?? [],
                        QmhFormAnswersValidator::REQUIRED_POLICY_ENFORCE
                    );
                    if (count($result['errors']) > 0) {
                        throw ValidationException::withMessages($result['errors']);
                    }

                    $revision->answers_json = $result['normalized'];
                    $revision->form_schema_json = $schema;
                }
            }

            $revision->status = 'in_review';
            $revision->diperiksa_oleh = $reviewerId;
            $revision->submitted_at = now();
            $revision->save();

            $lock->expires_at = now();
            $lock->save();

            $this->persistWorkflowEvent($revision->id, $actorId, 'submit_review', [
                'reviewer_id' => $reviewerId,
            ]);

            [$reviewTask, $actionCode] = $this->upsertQmhWorkflowTask(
                $revision,
                $actorId,
                $reviewerId,
                StaffTask::WORKFLOW_STAGE_REVIEW,
                [
                    'revision_id' => $revision->id,
                    'doc_code' => (string) ($revision->document?->doc_code ?? ''),
                    'approver_id' => $revision->disahkan_oleh,
                ]
            );

            SendQmhWorkflowTaskNotificationJob::dispatch($reviewTask->id, $actionCode)->afterCommit();

            return $revision->fresh();
        });
    }

    public function returnToDraft(QmhDocumentRevision $revision, int $actorId, string $note): QmhDocumentRevision
    {
        return DB::transaction(function () use ($revision, $actorId, $note) {
            $revision->refresh();

            if ($revision->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya revisi in_review yang bisa dikembalikan ke draft.',
                ]);
            }

            if ($revision->diperiksa_oleh !== $actorId) {
                throw new AuthorizationException('Hanya pemeriksa yang ditugaskan yang dapat mengembalikan draft.');
            }

            $revision->status = 'draft';
            $revision->reviewed_at = now();
            $revision->save();

            $lock = $revision->lock()->lockForUpdate()->first();
            if ($lock !== null && $lock->isActive()) {
                $lock->force_unlocked_by = $actorId;
                $lock->force_unlocked_reason = 'Workflow review return ke draft.';
                $lock->expires_at = now();
                $lock->save();

                $this->persistWorkflowEvent($revision->id, $actorId, 'unlock', [
                    'force' => true,
                    'reason' => 'Workflow review return ke draft.',
                    'trigger' => 'review_return',
                ]);
            }

            $this->persistWorkflowEvent($revision->id, $actorId, 'review_return', [
                'note' => $note,
            ]);

            $this->completeQmhWorkflowTasks($revision->id, StaffTask::WORKFLOW_STAGE_REVIEW, StaffTask::STATUS_COMPLETED);

            return $revision->fresh();
        });
    }

    public function passReview(QmhDocumentRevision $revision, int $actorId, int $approverId): QmhDocumentRevision
    {
        return DB::transaction(function () use ($revision, $actorId, $approverId) {
            $revision->refresh();

            if ($revision->status !== 'in_review') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya revisi in_review yang bisa diteruskan ke approval.',
                ]);
            }

            if ($revision->diperiksa_oleh !== $actorId) {
                throw new AuthorizationException('Hanya pemeriksa yang ditugaskan yang dapat meneruskan ke approval.');
            }

            if ($revision->dibuat_oleh === $approverId || $revision->diperiksa_oleh === $approverId) {
                throw ValidationException::withMessages([
                    'approver_id' => 'Pengesah harus berbeda dari pembuat dan pemeriksa.',
                ]);
            }

            $revision->status = 'in_approval';
            $revision->disahkan_oleh = $approverId;
            $revision->reviewed_at = now();
            $revision->save();

            $this->persistWorkflowEvent($revision->id, $actorId, 'review_pass', [
                'approver_id' => $approverId,
            ]);

            $this->completeQmhWorkflowTasks($revision->id, StaffTask::WORKFLOW_STAGE_REVIEW, StaffTask::STATUS_COMPLETED);

            [$approvalTask, $actionCode] = $this->upsertQmhWorkflowTask(
                $revision,
                $actorId,
                $approverId,
                StaffTask::WORKFLOW_STAGE_APPROVAL,
                [
                    'revision_id' => $revision->id,
                    'doc_code' => (string) ($revision->document?->doc_code ?? ''),
                ]
            );

            SendQmhWorkflowTaskNotificationJob::dispatch($approvalTask->id, $actionCode)->afterCommit();

            return $revision->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: StaffTask, 1: string}
     */
    private function upsertQmhWorkflowTask(
        QmhDocumentRevision $revision,
        int $actorId,
        int $assignedTo,
        string $stage,
        array $context
    ): array {
        $assignee = User::query()->find($assignedTo);
        if (! $assignee || ! $assignee->is_active) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Assignee workflow QMH tidak aktif atau tidak ditemukan.',
            ]);
        }

        $title = $stage === StaffTask::WORKFLOW_STAGE_REVIEW
            ? 'QMH Review Diperlukan'
            : 'QMH Approval Diperlukan';

        $description = sprintf(
            'Workflow QMH %s untuk revisi #%d (%s).',
            $stage,
            $revision->id,
            (string) ($revision->version_label ?? 'draft')
        );

        $actionCode = strtoupper(Str::random(10));
        $tokenHash = hash('sha256', $actionCode);

        $task = StaffTask::query()
            ->qmhWorkflow()
            ->where('source_ref_id', $revision->id)
            ->where('workflow_stage', $stage)
            ->where('assigned_to', $assignedTo)
            ->whereIn('status', [StaffTask::STATUS_PENDING, StaffTask::STATUS_IN_PROGRESS])
            ->lockForUpdate()
            ->first();

        $payload = [
            'title' => $title,
            'description' => $description,
            'assigned_to' => $assignedTo,
            'assigned_by' => $actorId,
            'priority' => StaffTask::PRIORITY_HIGH,
            'status' => StaffTask::STATUS_PENDING,
            'due_at' => now()->addDay(),
            'notify_whatsapp' => true,
            'notification_sent' => false,
            'notification_sent_at' => null,
            'source_module' => StaffTask::SOURCE_MODULE_QMH,
            'source_ref_type' => StaffTask::SOURCE_REF_TYPE_QMH_REVISION,
            'source_ref_id' => $revision->id,
            'workflow_stage' => $stage,
            'action_token_hash' => $tokenHash,
            'action_expires_at' => now()->addMinutes(30),
            'token_consumed_at' => null,
            'context_json' => $context,
        ];

        if ($task) {
            $task->update($payload);

            return [$task->fresh(), $actionCode];
        }

        return [StaffTask::query()->create($payload), $actionCode];
    }

    private function completeQmhWorkflowTasks(int $revisionId, string $stage, string $finalStatus): void
    {
        $now = now();

        StaffTask::query()
            ->forQmhRevision($revisionId)
            ->where('workflow_stage', $stage)
            ->whereIn('status', [StaffTask::STATUS_PENDING, StaffTask::STATUS_IN_PROGRESS])
            ->update([
                'status' => $finalStatus,
                'completed_at' => $finalStatus === StaffTask::STATUS_COMPLETED ? $now : null,
                'token_consumed_at' => $now,
            ]);
    }

    protected function persistWorkflowEvent(int $revisionId, int $actorId, string $eventType, array $payload): void
    {
        QmhWorkflowEvent::query()->create([
            'revision_id' => $revisionId,
            'event_type' => $eventType,
            'actor_id' => $actorId,
            'payload_json' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $templateMetadata
     * @return array<string, mixed>
     */
    private function mergeMissingLayoutConfig(array $schema, array $templateMetadata): array
    {
        $merged = $schema;
        $profileFromTemplate = QmhFrLayoutProfile::fromExplicitMetadata($templateMetadata);

        foreach (['layout_profile', 'shell_mode', 'orientation_policy', 'show_signoff_footer', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns'] as $key) {
            if (array_key_exists($key, $merged) && $merged[$key] !== null && $merged[$key] !== '') {
                continue;
            }

            $merged[$key] = $profileFromTemplate[$key] ?? null;
        }

        return $merged;
    }

    private function generateDuplicateDocCode(string $baseDocCode): string
    {
        $base = trim($baseDocCode);
        if ($base === '') {
            $base = 'QMH-FR-LEGACY';
        }

        $candidate = $base.'-V2';
        $suffix = 1;

        while (
            DB::table('qmh_documents')
                ->where('doc_code', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = sprintf('%s-V2-%d', $base, $suffix);
        }

        return $candidate;
    }
}
