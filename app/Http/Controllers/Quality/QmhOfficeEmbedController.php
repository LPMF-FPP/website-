<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhDocumentRevision;
use App\Services\Quality\QmhOfficeEditorService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QmhOfficeEmbedController extends Controller
{
    public function editor(Request $request, QmhOfficeEditorService $service): View
    {
        $token = $request->query('token');
        if (! is_string($token) || trim($token) === '') {
            abort(422, 'Token Office wajib diisi.');
        }

        try {
            $claims = $service->decodeToken($token);
        } catch (HttpException $exception) {
            abort(401, $exception->getMessage());
        }

        $revisionId = (int) ($claims['revision_id'] ?? 0);
        $userId = (int) ($claims['user_id'] ?? 0);

        if ($revisionId <= 0) {
            abort(404);
        }

        if ((int) $request->user()->id !== $userId) {
            abort(403);
        }

        $revision = QmhDocumentRevision::query()
            ->with(['document', 'lock'])
            ->findOrFail($revisionId);

        if ($revision->status !== 'draft') {
            abort(422, 'Sesi Office hanya tersedia untuk revisi draft.');
        }

        $lock = $revision->lock;
        if ($lock === null || ! $lock->isActive() || (int) $lock->locked_by !== (int) $request->user()->id) {
            abort(403, 'Sesi Office hanya dapat dibuka oleh pemilik lock aktif.');
        }

        return view('quality.office.editor', [
            'revision' => $revision,
            'token' => $token,
        ]);
    }
}
