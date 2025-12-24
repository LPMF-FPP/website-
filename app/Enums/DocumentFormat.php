<?php

namespace App\Enums;

enum DocumentFormat: string
{
    case PDF = 'pdf';
    case HTML = 'html';
    case DOCX = 'docx';

    /**
     * Get MIME type for this format
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::PDF => 'application/pdf',
            self::HTML => 'text/html',
            self::DOCX => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };
    }

    /**
     * Get file extension
     */
    public function extension(): string
    {
        return $this->value;
    }

    /**
     * Check if format is binary
     */
    public function isBinary(): bool
    {
        return match ($this) {
            self::PDF, self::DOCX => true,
            self::HTML => false,
        };
    }
}
