<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\ApproveQmhRevisionRequest;
use App\Http\Requests\Quality\CloseLegacyAndDuplicateToV2Request;
use App\Http\Requests\Quality\DownloadQmhRevisionRequest;
use App\Http\Requests\Quality\HeartbeatQmhRevisionRequest;
use App\Http\Requests\Quality\LockQmhRevisionRequest;
use App\Http\Requests\Quality\QmhPreviewPdfRequest;
use App\Http\Requests\Quality\RequestQmhTemplateFallbackRequest;
use App\Http\Requests\Quality\ReviewQmhRevisionRequest;
use App\Http\Requests\Quality\ReviewQmhTemplateFallbackRequest;
use App\Http\Requests\Quality\SaveQmhRevisionContentRequest;
use App\Http\Requests\Quality\SubmitQmhRevisionRequest;
use App\Http\Requests\Quality\UnlockQmhRevisionRequest;
use App\Models\QmhDocumentRevision;
use App\Services\Quality\QmhRevisionApprovalService;
use App\Services\Quality\QmhRevisionDownloadService;
use App\Services\Quality\QmhRevisionLockService;
use App\Services\Quality\QmhRevisionTransitionService;
use App\Services\Quality\QmhTemplateFallbackService;
use App\Support\QmhAnswerSanitizer;
use App\Support\QmhHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class QmhRevisionWorkflowController extends Controller
{
    public function lock(LockQmhRevisionRequest $request, QmhDocumentRevision $revision, QmhRevisionLockService $service): JsonResponse
    {
        $lock = $service->acquire($revision, (int) $request->user()->id);

        return response()->json([
            'message' => 'Lock aktif.',
            'data' => $lock,
        ]);
    }

    public function heartbeat(HeartbeatQmhRevisionRequest $request, QmhDocumentRevision $revision, QmhRevisionLockService $service): JsonResponse
    {
        $lock = $service->heartbeat($revision, (int) $request->user()->id);

        return response()->json([
            'message' => 'Heartbeat berhasil.',
            'data' => $lock,
        ]);
    }

    public function unlock(UnlockQmhRevisionRequest $request, QmhDocumentRevision $revision, QmhRevisionLockService $service): JsonResponse
    {
        $lock = $service->unlock(
            $revision,
            (int) $request->user()->id,
            (bool) $request->boolean('force'),
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Lock dibuka.',
            'data' => $lock,
        ]);
    }

    public function saveContent(SaveQmhRevisionContentRequest $request, QmhDocumentRevision $revision): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $revision): array {
            $lockedRevision = QmhDocumentRevision::query()
                ->whereKey($revision->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRevision->status !== 'draft') {
                return [
                    'status' => 'invalid_status',
                    'response' => response()->json([
                        'message' => 'Konten hanya dapat diubah saat status draft.',
                    ], 422),
                ];
            }

            $lockedRevision->loadMissing('document', 'lock');
            $isFormulir = (($lockedRevision->document?->doc_type ?? '') === 'formulir');

            $lock = $lockedRevision->lock;
            if ($lock === null || ! $lock->isActive()) {
                return [
                    'status' => 'lock_missing',
                    'response' => response()->json([
                        'message' => 'Tidak ada lock aktif untuk revisi ini.',
                    ], 409),
                ];
            }

            if ((int) $lock->locked_by !== (int) $request->user()->id) {
                return [
                    'status' => 'lock_forbidden',
                    'response' => response()->json([
                        'message' => 'Hanya pemilik lock aktif yang dapat menyimpan konten.',
                    ], 403),
                ];
            }

            $clientVersion = (int) $request->integer('content_version');
            $currentVersion = (int) ($lockedRevision->content_version ?? 1);
            if ($clientVersion !== $currentVersion) {
                return [
                    'status' => 'conflict',
                    'response' => response()->json([
                        'message' => 'Terjadi konflik versi konten. Muat ulang data terbaru sebelum menyimpan lagi.',
                        'conflict' => [
                            'received_content_version' => $clientVersion,
                            'current_content_version' => $currentVersion,
                        ],
                    ], 409),
                ];
            }

            $updates = [];
            if ($request->has('content_html')) {
                $sanitized = QmhHtmlSanitizer::sanitize($request->string('content_html')->toString());
                $updates['content_html'] = trim($sanitized) !== '' ? $sanitized : '<p></p>';
            }

            if ($request->has('content_css')) {
                $updates['content_css'] = $request->input('content_css');
            }

            if ($request->has('editor_json')) {
                $updates['editor_json'] = $request->input('editor_json');
            }

            if ($request->has('answers_json')) {
                $updates['answers_json'] = QmhAnswerSanitizer::sanitizeAnswersJson($request->input('answers_json'));
            }

            if ($request->has('form_schema_json')) {
                if (! $isFormulir) {
                    return [
                        'status' => 'invalid_schema_scope',
                        'response' => response()->json([
                            'message' => 'Schema pertanyaan hanya dapat diubah untuk dokumen Formulir (FR).',
                        ], 422),
                    ];
                }
                $updates['form_schema_json'] = $request->input('form_schema_json');
            }

            if ($request->has('dibuat_oleh') && (string) ($request->user()?->role ?? '') === 'admin') {
                $updates['dibuat_oleh'] = $request->input('dibuat_oleh');
            }
            if ($request->has('diperiksa_oleh')) {
                $updates['diperiksa_oleh'] = $request->input('diperiksa_oleh');
            }
            if ($request->has('disahkan_oleh')) {
                $updates['disahkan_oleh'] = $request->input('disahkan_oleh');
            }

            $updates['content_version'] = $currentVersion + 1;

            $lockedRevision->update($updates);

            return [
                'status' => 'ok',
                'response' => response()->json([
                    'message' => 'Konten revisi berhasil disimpan.',
                    'data' => $lockedRevision->fresh(),
                ]),
            ];
        });

        return $result['response'];
    }

    public function previewPdf(QmhPreviewPdfRequest $request, QmhDocumentRevision $revision, QmhRevisionDownloadService $service): Response
    {
        // Hydrate revision with preview data (but don't save)
        $validated = $request->validated();

        $revision->fill([
            'change_summary' => $validated['change_summary'] ?? $revision->change_summary,
            'answers_json' => QmhAnswerSanitizer::sanitizeAnswersJson($validated['answers_json'] ?? ($revision->answers_json ?? [])),
            'content_html' => isset($validated['content_html']) && is_string($validated['content_html']) && trim($validated['content_html']) !== ''
                ? $validated['content_html']
                : ($revision->content_html ?? '<p></p>'),
        ]);

        if (array_key_exists('form_schema_json', $validated) && is_array($validated['form_schema_json'])) {
            $revision->form_schema_json = $validated['form_schema_json'];
        }

        if (array_key_exists('dibuat_oleh', $validated)) {
            $revision->dibuat_oleh = $validated['dibuat_oleh'];
        }
        if (array_key_exists('diperiksa_oleh', $validated)) {
            $revision->diperiksa_oleh = $validated['diperiksa_oleh'];
        }
        if (array_key_exists('disahkan_oleh', $validated)) {
            $revision->disahkan_oleh = $validated['disahkan_oleh'];
        }

        // Override effective_date to be null for draft preview
        if ($revision->status !== 'published') {
            $revision->effective_date = null;
        }

        // Ensure relations are loaded
        $revision->loadMissing(['document', 'template', 'createdBy', 'reviewedBy', 'approvedBy']);

        $binary = $service->renderPdfBinary($revision, 'DRAFT PREVIEW');

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function submit(SubmitQmhRevisionRequest $request, QmhDocumentRevision $revision, QmhRevisionTransitionService $service): JsonResponse
    {
        $updated = $service->submitForReview(
            $revision,
            (int) $request->user()->id,
            (int) $request->integer('reviewer_id')
        );

        return response()->json([
            'message' => 'Revisi dikirim ke review.',
            'data' => $updated,
        ]);
    }

    public function requestTemplateFallback(
        RequestQmhTemplateFallbackRequest $request,
        QmhDocumentRevision $revision,
        QmhTemplateFallbackService $service
    ): JsonResponse {
        $fallback = $service->requestForRevision(
            $revision,
            (int) $request->user()->id,
            $request->input('layout_profile'),
            $request->input('note')
        );

        return response()->json([
            'message' => 'Permintaan fallback template berhasil dibuat.',
            'data' => $fallback,
        ]);
    }

    public function reviewTemplateFallback(
        ReviewQmhTemplateFallbackRequest $request,
        QmhDocumentRevision $revision,
        QmhTemplateFallbackService $service
    ): JsonResponse {
        $validated = $request->validated();

        $fallback = $service->reviewLatestRequest(
            $revision,
            (int) $request->user()->id,
            (string) $validated['action'],
            isset($validated['fallback_template_id']) ? (int) $validated['fallback_template_id'] : null,
            $validated['note'] ?? null
        );

        return response()->json([
            'message' => 'Review fallback template berhasil diproses.',
            'data' => $fallback,
        ]);
    }

    public function review(ReviewQmhRevisionRequest $request, QmhDocumentRevision $revision, QmhRevisionTransitionService $service): JsonResponse
    {
        if ($request->string('action')->toString() === 'return') {
            $updated = $service->returnToDraft(
                $revision,
                (int) $request->user()->id,
                $request->string('note')->toString()
            );

            return response()->json([
                'message' => 'Revisi dikembalikan ke draft.',
                'data' => $updated,
            ]);
        }

        $updated = $service->passReview(
            $revision,
            (int) $request->user()->id,
            (int) $request->integer('approver_id')
        );

        return response()->json([
            'message' => 'Revisi diteruskan ke approval.',
            'data' => $updated,
        ]);
    }

    public function approve(ApproveQmhRevisionRequest $request, QmhDocumentRevision $revision, QmhRevisionApprovalService $service): JsonResponse
    {
        $validated = $request->validated();

        $updated = $service->approve(
            $revision,
            (int) $request->user()->id,
            (bool) ($validated['promote_to_new_edition'] ?? false),
            $validated['reason'] ?? null,
            isset($validated['checker_status']) ? (string) $validated['checker_status'] : null,
            is_array($validated['checker_payload'] ?? null) ? $validated['checker_payload'] : null,
            isset($validated['attestation_actor']) ? (string) $validated['attestation_actor'] : null,
            isset($validated['attestation_reason']) ? (string) $validated['attestation_reason'] : null,
            isset($validated['incident_ref']) ? (string) $validated['incident_ref'] : null
        );

        return response()->json([
            'message' => 'Revisi berhasil disahkan dan dipublish.',
            'data' => $updated,
        ]);
    }

    public function closeLegacyAndDuplicateToV2(
        CloseLegacyAndDuplicateToV2Request $request,
        QmhDocumentRevision $revision,
        QmhRevisionTransitionService $service
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->closeLegacyAndDuplicateToV2(
            $revision,
            (int) $request->user()->id,
            (string) $validated['idempotency_key'],
            (string) $validated['reason'],
            is_array($validated['context'] ?? null) ? $validated['context'] : null
        );

        return response()->json([
            'message' => 'Cutover legacy ke FR-v2 berhasil diproses.',
            'data' => $result,
        ]);
    }

    public function download(DownloadQmhRevisionRequest $request, QmhDocumentRevision $revision, QmhRevisionDownloadService $service): Response
    {
        $result = $service->generateAndLogDownload(
            $revision,
            (int) $request->user()->id,
            $request->string('copy_type')->toString(),
            $request->input('reason'),
            $request->input('distribution_target'),
            $request->ip(),
            $request->userAgent()
        );

        return response($result['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
