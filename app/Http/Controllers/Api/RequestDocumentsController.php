<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\TestRequest;
use App\Services\DocumentService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class RequestDocumentsController extends Controller
{
    public function index(TestRequest $testRequest): JsonResponse
    {
        Log::info('API: Request documents called', [
            'request_id' => $testRequest->id,
            'user_id' => auth()->id(),
            'user_role' => auth()->user()->role ?? 'guest',
            'investigator_id' => $testRequest->investigator_id,
        ]);

        Gate::authorize('viewDocuments', $testRequest->investigator);

        $documents = $testRequest->documents()
            ->with('investigator:id,name')
            ->latest()
            ->get()
            ->filter(fn (Document $document) => Gate::allows('view', $document))
            ->map(function (Document $document) {
                return [
                    'id' => $document->id,
                    'name' => $document->original_filename ?? $document->filename ?? 'Dokumen',
                    'type' => $document->document_type,
                    'mime' => $document->mime_type,
                    'source' => $document->source,
                    'preview_url' => route('investigator.documents.show', ['document' => $document->id]),
                    'download_url' => URL::temporarySignedRoute(
                        'investigator.documents.download',
                        now()->addMinutes(15),
                        ['document' => $document->id]
                    ),
                    'created_at' => optional($document->created_at)->toIso8601String(),
                ];
            })
            ->values();

        Log::info('API: Documents retrieved', [
            'request_id' => $testRequest->id,
            'document_count' => $documents->count(),
        ]);

        return response()->json([
            'data' => $documents,
        ]);
    }

    public function destroy(TestRequest $testRequest, string $type): JsonResponse
    {
        // Find document by test_request_id and document_type
        $document = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', $type)
            ->firstOrFail();

        Gate::authorize('delete', $document);

        $documentService = app(DocumentService::class);
        $name = $document->original_filename ?? $document->filename;
        $id = $document->id;

        $documentService->delete($document);

        Audit::log('DELETE_DOCUMENT_API', (string) $id, null, [
            'name' => $name,
            'type' => $document->document_type,
            'request_id' => $testRequest->id,
        ]);

        return response()->json([
            'ok' => true,
            'requestId' => $testRequest->id,
            'removed' => $type,
        ]);
    }
}
