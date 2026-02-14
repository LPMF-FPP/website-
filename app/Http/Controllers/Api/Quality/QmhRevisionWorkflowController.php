<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\ApproveQmhRevisionRequest;
use App\Http\Requests\Quality\DownloadQmhRevisionRequest;
use App\Http\Requests\Quality\HeartbeatQmhRevisionRequest;
use App\Http\Requests\Quality\LockQmhRevisionRequest;
use App\Http\Requests\Quality\ReviewQmhRevisionRequest;
use App\Http\Requests\Quality\SaveQmhRevisionContentRequest;
use App\Http\Requests\Quality\SubmitQmhRevisionRequest;
use App\Http\Requests\Quality\UnlockQmhRevisionRequest;
use App\Models\QmhDocumentRevision;
use App\Services\Quality\QmhRevisionApprovalService;
use App\Services\Quality\QmhRevisionDownloadService;
use App\Services\Quality\QmhRevisionLockService;
use App\Services\Quality\QmhRevisionTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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
        if ($revision->status !== 'draft') {
            return response()->json([
                'message' => 'Konten hanya dapat diubah saat status draft.',
            ], 422);
        }

        $lock = $revision->lock;
        if ($lock === null || ! $lock->isActive()) {
            return response()->json([
                'message' => 'Tidak ada lock aktif untuk revisi ini.',
            ], 409);
        }

        if ((int) $lock->locked_by !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Hanya pemilik lock aktif yang dapat menyimpan konten.',
            ], 403);
        }

        $updates = [];
        if ($request->has('content_html')) {
            $updates['content_html'] = $request->string('content_html')->toString();
        }

        if ($request->has('content_css')) {
            $updates['content_css'] = $request->input('content_css');
        }

        if ($request->has('editor_json')) {
            $updates['editor_json'] = $request->input('editor_json');
        }

        if ($request->has('answers_json')) {
            $updates['answers_json'] = $request->input('answers_json');
        }

        if ($request->has('effective_date')) {
            $updates['effective_date'] = $request->input('effective_date');
        }

        $revision->update($updates);

        return response()->json([
            'message' => 'Konten revisi berhasil disimpan.',
            'data' => $revision->fresh(),
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
        $updated = $service->approve(
            $revision,
            (int) $request->user()->id,
            (bool) $request->boolean('promote_to_new_edition'),
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Revisi berhasil disahkan dan dipublish.',
            'data' => $updated,
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
