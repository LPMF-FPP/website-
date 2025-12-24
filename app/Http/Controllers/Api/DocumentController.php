<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Search\Document;

class DocumentController extends Controller
{
    public function __invoke(Document $document)
    {
        $this->authorize('view', $document);

        return response()->json([
            'id' => $document->id,
            'doc_type' => $document->doc_type,
            'ba_no' => $document->ba_no,
            'title' => $document->title,
            'lp_no' => $document->lp_no,
            'doc_date' => optional($document->doc_date)->format('Y-m-d'),
            'created_at' => optional($document->created_at)->toISOString(),
        ]);
    }
}
