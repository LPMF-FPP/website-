<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\QmhWorkflowEvent;
use App\Support\QmhFormAnswersValidator;
use App\Support\QmhFrLayoutProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QmhRevisionTransitionService
{
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

                $template = null;
                if ((int) ($revision->template_id ?? 0) > 0) {
                    $template = QmhTemplate::query()->find((int) $revision->template_id);
                }

                $metadata = is_array($template?->metadata) ? $template->metadata : [];
                if (! is_array($schema)) {
                    $schema = $metadata['form_schema'] ?? null;
                }

                if (is_array($schema)) {
                    $schema = $this->mergeMissingLayoutConfig($schema, $metadata);

                    $result = QmhFormAnswersValidator::validateAndNormalize($schema, $revision->answers_json ?? []);
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

            $this->persistWorkflowEvent($revision->id, $actorId, 'review_return', [
                'note' => $note,
            ]);

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

            return $revision->fresh();
        });
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
        $profileFromTemplate = QmhFrLayoutProfile::fromSchema($templateMetadata);

        foreach (['layout_profile', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns'] as $key) {
            if (array_key_exists($key, $merged) && $merged[$key] !== null && $merged[$key] !== '') {
                continue;
            }

            $merged[$key] = $profileFromTemplate[$key] ?? null;
        }

        return $merged;
    }
}
