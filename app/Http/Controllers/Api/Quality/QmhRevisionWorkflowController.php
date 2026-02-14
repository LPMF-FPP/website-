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
use App\Services\Quality\QmhOfficeEditorService;
use App\Services\Quality\QmhRevisionApprovalService;
use App\Services\Quality\QmhRevisionDownloadService;
use App\Services\Quality\QmhRevisionLockService;
use App\Services\Quality\QmhRevisionTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QmhRevisionWorkflowController extends Controller
{
    public function officeSession(Request $request, QmhDocumentRevision $revision, QmhOfficeEditorService $service): JsonResponse
    {
        $session = $service->createSession($revision, $request->user());

        return response()->json([
            'message' => 'Sesi Office siap digunakan.',
            'data' => $session,
        ]);
    }

    public function officeCallback(Request $request, QmhDocumentRevision $revision, QmhOfficeEditorService $service): JsonResponse
    {
        $callbackHostHeader = (string) config('quality.office.callback_host_header', 'X-Office-Callback-Host');
        $callbackHost = $request->header($callbackHostHeader);

        $result = $service->handleCallback($revision, $request->all(), $callbackHost);

        return response()->json([
            'error' => 0,
            'message' => 'Callback Office diterima.',
            'data' => $result,
        ]);
    }

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

    public function downloadDocx(Request $request, QmhDocumentRevision $revision): StreamedResponse
    {
        $revision->loadMissing(['document', 'lock', 'template']);

        if ($revision->status !== 'draft') {
            abort(422, 'Dokumen hanya dapat diedit saat status draft.');
        }

        $lock = $revision->lock;
        if ($lock === null || ! $lock->isActive()) {
            abort(409, 'Tidak ada lock aktif untuk revisi ini.');
        }

        if ((int) $lock->locked_by !== (int) $request->user()->id) {
            abort(403, 'Hanya pemilik lock aktif yang dapat mengakses file DOCX.');
        }

        $disk = $revision->template?->storage_disk ?? 'local';
        $path = $revision->source_docx_path;

        if (! is_string($path) || trim($path) === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'File DOCX tidak ditemukan.');
        }

        $filename = sprintf('%s-%s.docx', $revision->document?->doc_code ?? 'qmh-document', $revision->version_label);

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function saveDocx(Request $request, QmhDocumentRevision $revision): JsonResponse
    {
        $revision->loadMissing(['document', 'lock', 'template']);

        if ($revision->status !== 'draft') {
            return response()->json([
                'message' => 'Dokumen hanya dapat diedit saat status draft.',
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
                'message' => 'Hanya pemilik lock aktif yang dapat menyimpan file DOCX.',
            ], 403);
        }

        $disk = $revision->template?->storage_disk ?? 'local';

        $docCode = $revision->document?->doc_code ?? 'qmh-document';
        $versionLabel = $revision->version_label ?: 'E1-R0';
        $targetPath = $revision->source_docx_path;
        if (! is_string($targetPath) || trim($targetPath) === '') {
            $targetPath = sprintf('qmh/%s/%s/source.docx', $docCode, $versionLabel);
        }

        $dir = trim((string) dirname($targetPath), '.');
        $name = (string) basename($targetPath);

        $uploaded = $request->file('file');
        $checksum = null;
        if ($uploaded) {
            $uploaded->storeAs($dir, $name, $disk);

            $absolutePath = Storage::disk($disk)->path($targetPath);
            $checksum = is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null;
        } else {
            $binary = $request->getContent();
            if (! is_string($binary) || $binary === '') {
                return response()->json([
                    'message' => 'File DOCX wajib diunggah.',
                ], 422);
            }

            Storage::disk($disk)->put($targetPath, $binary);
            $checksum = hash('sha256', $binary);
        }

        $revision->forceFill([
            'source_docx_path' => $targetPath,
            'source_docx_checksum' => $checksum,
            'source_docx_version' => max(1, (int) $revision->source_docx_version) + 1,
            'last_autosaved_at' => now(),
            'export_pdf_from_docx' => true,
        ])->save();

        return response()->json([
            'message' => 'DOCX berhasil disimpan.',
            'data' => $revision->fresh(),
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
