<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\HeartbeatQmhRevisionRequest;
use App\Http\Requests\Quality\LockQmhRevisionRequest;
use App\Http\Requests\Quality\ReviewQmhRevisionRequest;
use App\Http\Requests\Quality\SubmitQmhRevisionRequest;
use App\Http\Requests\Quality\UnlockQmhRevisionRequest;
use App\Models\QmhDocumentRevision;
use App\Services\Quality\QmhRevisionLockService;
use App\Services\Quality\QmhRevisionTransitionService;
use Illuminate\Http\JsonResponse;

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
}
