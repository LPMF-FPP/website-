<?php

namespace App\Services\DocumentGeneration;

use App\Enums\DocumentFormat;

class RenderedDocument
{
    public function __construct(
        public readonly string $content,
        public readonly string $mimeType,
        public readonly string $filename,
        public readonly DocumentFormat $format,
        public readonly ?int $templateId = null,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Get content as binary string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get content size in bytes
     */
    public function getSize(): int
    {
        return strlen($this->content);
    }

    /**
     * Create HTTP response for download
     */
    public function toDownloadResponse()
    {
        return response($this->content, 200, [
            'Content-Type' => $this->mimeType,
            'Content-Disposition' => 'attachment; filename="' . $this->filename . '"',
            'Content-Length' => $this->getSize(),
        ]);
    }

    /**
     * Create HTTP response for inline preview
     */
    public function toInlineResponse()
    {
        return response($this->content, 200, [
            'Content-Type' => $this->mimeType,
            'Content-Disposition' => 'inline; filename="' . $this->filename . '"',
            'Content-Length' => $this->getSize(),
        ]);
    }

    /**
     * Save to storage
     */
    public function saveToStorage(string $disk, string $path): bool
    {
        return \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $this->content);
    }
}
