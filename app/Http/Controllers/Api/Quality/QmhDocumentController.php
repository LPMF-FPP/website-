<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreQmhDocumentRequest;
use App\Models\QmhDocument;
use App\Services\Quality\QmhDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QmhDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = QmhDocument::query()
            ->with('currentRevision')
            ->search($request->string('search')->toString())
            ->when($request->filled('clause'), function ($query) use ($request) {
                $query->where('clause', (int) $request->input('clause'));
            })
            ->when($request->filled('doc_type'), function ($query) use ($request) {
                $docType = $request->string('doc_type')->toString();
                $query->where('doc_type', $docType === 'fr' ? 'formulir' : $docType);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->whereHas('currentRevision', function ($revisionQuery) use ($request) {
                    $revisionQuery->where('status', $request->string('status')->toString());
                });
            })
            ->when($request->filled('edition_number'), function ($query) use ($request) {
                $query->whereHas('currentRevision', function ($revisionQuery) use ($request) {
                    $revisionQuery->where('edition_number', (int) $request->input('edition_number'));
                });
            })
            ->when($request->filled('revision_number'), function ($query) use ($request) {
                $query->whereHas('currentRevision', function ($revisionQuery) use ($request) {
                    $revisionQuery->where('revision_number', (int) $request->input('revision_number'));
                });
            })
            ->orderBy('doc_code')
            ->paginate(15)
            ->appends($request->query());

        return response()->json($documents);
    }

    public function store(StoreQmhDocumentRequest $request, QmhDocumentService $service): JsonResponse
    {
        try {
            $document = $service->createDraft($request->validated(), (int) $request->user()->id);

            return response()->json([
                'message' => 'Dokumen QMH berhasil dibuat.',
                'data' => $document,
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Gagal membuat dokumen QMH', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal membuat dokumen QMH.',
            ], 500);
        }
    }
}
