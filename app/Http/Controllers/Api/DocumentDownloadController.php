<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Search\Document;
use Illuminate\Support\Facades\Storage;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document)
    {
        $this->authorize('download', $document);

        $disk = config('search.documents_disk', 'documents');
        $path = (string) $document->file_path;

        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $base = $document->ba_no ?: ('document_' . $document->id);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
        if ($ext) {
            $filename .= '.' . $ext;
        }

        return Storage::disk($disk)->download($path, $filename, [
            'Content-Type' => Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
        ]);
    }
}
