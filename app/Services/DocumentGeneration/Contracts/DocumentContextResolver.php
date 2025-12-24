<?php

namespace App\Services\DocumentGeneration\Contracts;

use App\Enums\DocumentType;

interface DocumentContextResolver
{
    /**
     * Resolve context data for rendering a document
     *
     * @param mixed $contextId The ID or object to resolve context from
     * @return array Context data for template rendering
     */
    public function resolve($contextId): array;

    /**
     * Get sample/mock context for preview purposes
     *
     * @return array Sample context data
     */
    public function getSampleContext(): array;

    /**
     * Get the document type this resolver handles
     *
     * @return DocumentType
     */
    public function getDocumentType(): DocumentType;
}
