<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DocumentDeleteController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    public function __invoke(Document $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        $name = $document->original_filename ?? $document->filename;
        $id = $document->id;

        $this->documents->delete($document);

        Audit::log('DELETE_DOCUMENT_API', (string) $id, null, [
            'name' => $name,
            'type' => $document->document_type,
        ]);

        return response()->json([
            'deleted' => true,
            'id' => $id,
        ]);
    }
}
