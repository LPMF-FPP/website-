<?php

namespace App\Http\Controllers;

use App\Models\ExpertWitnessDocument;
use Illuminate\Support\Facades\Storage;

class ExpertWitnessDocumentController extends Controller
{
    public function download(ExpertWitnessDocument $document)
    {
        $this->authorize('view', $document->request);

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }
}
