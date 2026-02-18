<?php

namespace App\Services\Quality;

use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\QmhTemplateFallbackRequest;
use App\Support\Audit;
use App\Support\QmhFrLayoutProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QmhTemplateFallbackService
{
    public function requestForRevision(QmhDocumentRevision $revision, int $actorId, ?string $layoutProfile, ?string $note): QmhTemplateFallbackRequest
    {
        return DB::transaction(function () use ($revision, $actorId, $layoutProfile, $note): QmhTemplateFallbackRequest {
            $revision->loadMissing('document', 'template');

            $requestedClause = (int) ($revision->document?->clause ?? 0);
            $requestedDocType = $this->normalizeDocumentType((string) ($revision->document?->doc_type ?? ''));

            if ($requestedClause <= 0 || ! in_array($requestedClause, [4, 5, 6, 7, 8], true)) {
                throw ValidationException::withMessages([
                    'clause' => 'Klausul dokumen tidak valid untuk proses fallback.',
                ]);
            }

            if ($requestedClause === 4) {
                throw ValidationException::withMessages([
                    'clause' => 'Fallback template hanya untuk klausul selain 4.',
                ]);
            }

            $requestedLayoutProfile = null;
            if ($requestedDocType === 'fr') {
                $requestedLayoutProfile = QmhFrLayoutProfile::normalizeProfile(
                    $layoutProfile
                    ?? (string) data_get($revision->form_schema_json, 'layout_profile')
                    ?? (string) data_get($revision->template?->metadata, 'layout_profile')
                );
            }

            $fallbackTemplate = $this->resolveFallbackTemplate(
                $requestedDocType,
                $requestedLayoutProfile,
                null
            );

            if ($fallbackTemplate === null) {
                throw ValidationException::withMessages([
                    'fallback_template' => 'Template fallback clause 4 yang sesuai belum tersedia.',
                ]);
            }

            $fallback = QmhTemplateFallbackRequest::query()->create([
                'document_id' => $revision->document_id,
                'revision_id' => $revision->id,
                'requested_clause' => $requestedClause,
                'requested_doc_type' => $requestedDocType,
                'requested_layout_profile' => $requestedLayoutProfile,
                'fallback_clause' => 4,
                'fallback_template_id' => $fallbackTemplate->id,
                'status' => 'requested',
                'requested_by' => $actorId,
                'decision_note' => $note,
                'requested_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);

            Audit::log('QMH_TEMPLATE_FALLBACK_REQUESTED', (string) $fallback->id, null, [
                'document_id' => $fallback->document_id,
                'revision_id' => $fallback->revision_id,
                'requested_clause' => $fallback->requested_clause,
                'requested_doc_type' => $fallback->requested_doc_type,
                'requested_layout_profile' => $fallback->requested_layout_profile,
                'fallback_template_id' => $fallback->fallback_template_id,
                'expires_at' => $fallback->expires_at,
            ]);

            return $fallback->fresh();
        });
    }

    public function reviewLatestRequest(
        QmhDocumentRevision $revision,
        int $actorId,
        string $action,
        ?int $fallbackTemplateId,
        ?string $note
    ): QmhTemplateFallbackRequest {
        return DB::transaction(function () use ($revision, $actorId, $action, $fallbackTemplateId, $note): QmhTemplateFallbackRequest {
            $revision->loadMissing('document');

            $pending = QmhTemplateFallbackRequest::query()
                ->where('document_id', $revision->document_id)
                ->where('status', 'requested')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                throw ValidationException::withMessages([
                    'fallback' => 'Tidak ada fallback request aktif untuk direview.',
                ]);
            }

            if ($action === 'approve') {
                $template = $this->resolveFallbackTemplate(
                    (string) $pending->requested_doc_type,
                    is_string($pending->requested_layout_profile) ? $pending->requested_layout_profile : null,
                    $fallbackTemplateId
                );

                if ($template === null) {
                    throw ValidationException::withMessages([
                        'fallback_template_id' => 'Template fallback yang disetujui tidak valid.',
                    ]);
                }

                $pending->status = 'approved';
                $pending->fallback_template_id = $template->id;
                $pending->decision_note = $note;
            } else {
                $pending->status = 'rejected';
                $pending->decision_note = $note;
            }

            $pending->decided_by = $actorId;
            $pending->decided_at = now();
            $pending->save();

            Audit::log('QMH_TEMPLATE_FALLBACK_REVIEWED', (string) $pending->id, [
                'status' => 'requested',
            ], [
                'status' => $pending->status,
                'fallback_template_id' => $pending->fallback_template_id,
                'decided_by' => $pending->decided_by,
                'decided_at' => $pending->decided_at,
                'decision_note' => $pending->decision_note,
            ]);

            return $pending->fresh();
        });
    }

    public function expirePendingRequests(): int
    {
        $expired = 0;

        QmhTemplateFallbackRequest::query()
            ->where('status', 'requested')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$expired): void {
                foreach ($items as $item) {
                    $item->status = 'expired';
                    $item->save();
                    $expired++;

                    Audit::log('QMH_TEMPLATE_FALLBACK_EXPIRED', (string) $item->id, [
                        'status' => 'requested',
                    ], [
                        'status' => 'expired',
                    ]);
                }
            });

        return $expired;
    }

    private function normalizeDocumentType(string $docType): string
    {
        return match ($docType) {
            'formulir', 'fr' => 'fr',
            default => $docType,
        };
    }

    private function resolveFallbackTemplate(string $requestedDocType, ?string $requestedLayoutProfile, ?int $fallbackTemplateId): ?QmhTemplate
    {
        $query = QmhTemplate::query()
            ->where('doc_type', $requestedDocType)
            ->where('clause', 4)
            ->where('is_active', true)
            ->orderByDesc('version');

        if ($fallbackTemplateId !== null && $fallbackTemplateId > 0) {
            $query->whereKey($fallbackTemplateId);
        }

        $templates = $query->get();
        if ($requestedDocType !== 'fr') {
            return $templates->first();
        }

        $targetProfile = $requestedLayoutProfile !== null
            ? QmhFrLayoutProfile::normalizeProfile($requestedLayoutProfile)
            : QmhFrLayoutProfile::defaultAuthoringProfile();
        $targetIdentity = QmhFrLayoutProfile::identityKey([
            'layout_profile' => $targetProfile,
        ]);

        return $templates->first(function (QmhTemplate $template) use ($targetProfile, $targetIdentity): bool {
            $metadata = is_array($template->metadata) ? $template->metadata : [];
            $layout = QmhFrLayoutProfile::fromMetadata($metadata);
            $profile = isset($layout['layout_profile'])
                ? (string) $layout['layout_profile']
                : QmhFrLayoutProfile::defaultAuthoringProfile();
            $identity = QmhFrLayoutProfile::identityKey($metadata);

            return $profile === $targetProfile && $identity === $targetIdentity;
        });
    }
}
