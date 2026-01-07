<?php

namespace App\Observers;

use App\Models\Document;
use App\Support\ActivityLogger;

class DocumentObserver
{
    public function created(Document $document): void
    {
        ActivityLogger::log(
            'DOCUMENT_UPLOADED',
            null,
            $document,
            null,
            $this->snapshot($document),
            [
                'document_type' => $document->document_type,
                'source' => $document->source,
                'original_filename' => $document->original_filename,
                'storage_disk' => $document->storage_disk,
            ]
        );
    }

    public function deleted(Document $document): void
    {
        ActivityLogger::log(
            'DOCUMENT_DELETED',
            null,
            $document,
            $this->snapshot($document),
            null,
            [
                'document_type' => $document->document_type,
                'source' => $document->source,
                'original_filename' => $document->original_filename,
                'storage_disk' => $document->storage_disk,
            ]
        );
    }

    private function snapshot(Document $document): array
    {
        return [
            'id' => $document->id,
            'test_request_id' => $document->test_request_id,
            'investigator_id' => $document->investigator_id,
            'document_type' => $document->document_type,
            'source' => $document->source,
            'file_path' => $document->file_path ?? $document->path,
            'original_filename' => $document->original_filename,
            'generated_by' => $document->generated_by,
        ];
    }
}
