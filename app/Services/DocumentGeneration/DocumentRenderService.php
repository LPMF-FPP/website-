<?php

namespace App\Services\DocumentGeneration;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Repositories\DocumentTemplateRepository;
use App\Services\DocumentTemplates\DocumentTemplateRenderService;
use App\Services\DocumentGeneration\Contracts\DocumentContextResolver;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class DocumentRenderService
{
    private array $resolvers = [];

    public function __construct(
        private readonly DocumentTemplateRepository $templateRepository,
        private readonly DocumentTemplateRenderService $templateRenderService
    ) {
    }

    /**
     * Register a context resolver for a document type
     */
    public function registerResolver(DocumentContextResolver $resolver): void
    {
        $this->resolvers[$resolver->getDocumentType()->value] = $resolver;
    }

    /**
     * Render a document with given context
     *
     * @param DocumentType $type
     * @param mixed $contextId
     * @param DocumentFormat|null $format
     * @param array $options Additional rendering options
     * @return RenderedDocument
     * @throws \Exception
     */
    public function render(
        DocumentType $type,
        $contextId,
        ?DocumentFormat $format = null,
        array $options = []
    ): RenderedDocument {
        $format = $format ?? $type->defaultFormat();

        // Get context data
        $context = $this->resolveContext($type, $contextId);

        // Get template
        $template = $this->getTemplate($type, $format);

        // Render based on format
        $result = match ($format) {
            DocumentFormat::PDF => $this->renderPdf($template, $context, $options, $type),
            DocumentFormat::HTML => $this->renderHtml($template, $context, $options, $type),
            DocumentFormat::DOCX => $this->renderDocx($template, $context, $options, $type),
        };

        // Log rendering
        if ($options['audit'] ?? true) {
            Audit::log('DOCUMENT_RENDERED', $type->value, [
                'format' => $format->value,
                'template_id' => $template?->id,
                'context_id' => $contextId,
            ]);
        }

        return $result;
    }

    /**
     * Render a document for preview (using sample context)
     */
    public function renderPreview(
        DocumentType $type,
        ?DocumentFormat $format = null,
        array $options = []
    ): RenderedDocument {
        $format = $format ?? $type->defaultFormat();

        // Get sample context
        $context = $this->resolveSampleContext($type);

        // Get template
        $template = $this->getTemplate($type, $format);

        // Render based on format
        return match ($format) {
            DocumentFormat::PDF => $this->renderPdf($template, $context, $options, $type),
            DocumentFormat::HTML => $this->renderHtml($template, $context, $options, $type),
            DocumentFormat::DOCX => $this->renderDocx($template, $context, $options, $type),
        };
    }

    /**
     * Resolve context data for a document
     */
    private function resolveContext(DocumentType $type, $contextId): array
    {
        $resolver = $this->getResolver($type);
        return $resolver->resolve($contextId);
    }

    /**
     * Resolve sample context for preview
     */
    private function resolveSampleContext(DocumentType $type): array
    {
        $resolver = $this->getResolver($type);
        try {
            return $resolver->getSampleContext();
        } catch (\Throwable $e) {
            Log::error("Failed to get sample context for {$type->value}", ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to get sample context for document type: {$type->value}", 0, $e);
        }
    }

    /**
     * Expose sample context for template previews
     */
    public function getSampleContext(DocumentType $type): array
    {
        return $this->resolveSampleContext($type);
    }

    /**
     * Get context resolver for document type
     */
    private function getResolver(DocumentType $type): DocumentContextResolver
    {
        if (!isset($this->resolvers[$type->value])) {
            throw new \InvalidArgumentException("No context resolver registered for document type: {$type->value}. Available types: " . implode(', ', array_keys($this->resolvers)));
        }

        return $this->resolvers[$type->value];
    }

    /**
     * Get template (with fallback to legacy view)
     */
    private function getTemplate(DocumentType $type, DocumentFormat $format): ?DocumentTemplate
    {
        $template = $this->templateRepository->getActiveTemplate($type, $format);

        if (!$template) {
            Log::warning("No active template found for {$type->value}/{$format->value}, will use legacy fallback");
        }

        return $template;
    }

    /**
     * Render HTML output
     */
    private function renderHtml(?DocumentTemplate $template, array $context, array $options, DocumentType $type): RenderedDocument
    {
        if ($template) {
            return $this->templateRenderService->renderHtml($template, $type, $context, $options);
        }

        // Render legacy view
        $viewName = $type->legacyView();
        if (!$viewName) {
            throw new \InvalidArgumentException("No template uploaded and no legacy view configured for document type: {$type->value}");
        }
        if (!View::exists($viewName)) {
            throw new \InvalidArgumentException("Legacy view '{$viewName}' does not exist for document type: {$type->value}");
        }
        try {
            $html = view($viewName, $context)->render();
        } catch (\Throwable $e) {
            Log::error("Failed to render legacy view: {$viewName}", ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to render legacy view '{$viewName}': {$e->getMessage()}", 0, $e);
        }

        $filename = $this->generateFilename($type, 'html', $context);

        return new RenderedDocument(
            content: $html,
            mimeType: DocumentFormat::HTML->mimeType(),
            filename: $filename,
            format: DocumentFormat::HTML,
            templateId: null
        );
    }

    /**
     * Render PDF output
     */
    private function renderPdf(?DocumentTemplate $template, array $context, array $options, DocumentType $type): RenderedDocument
    {
        if ($template) {
            return $this->templateRenderService->renderPdf($template, $type, $context, $options);
        }

        // First render HTML from legacy view
        $htmlDoc = $this->renderHtml(null, $context, array_merge($options, ['audit' => false]), $type);

        // Convert to PDF
        $pdf = Pdf::loadHTML($htmlDoc->content)
            ->setPaper($options['paper'] ?? 'a4')
            ->setOption('isRemoteEnabled', $options['isRemoteEnabled'] ?? true)
            ->setOption('isHtml5ParserEnabled', $options['isHtml5ParserEnabled'] ?? true)
            ->setOption('dpi', $options['dpi'] ?? 96);

        $pdfBinary = $pdf->output();

        $filename = $this->generateFilename($type, 'pdf', $context);

        return new RenderedDocument(
            content: $pdfBinary,
            mimeType: DocumentFormat::PDF->mimeType(),
            filename: $filename,
            format: DocumentFormat::PDF,
            templateId: null
        );
    }

    /**
     * Render DOCX output
     */
    private function renderDocx(?DocumentTemplate $template, array $context, array $options, DocumentType $type): RenderedDocument
    {
        // Note: DOCX generation is not implemented in current codebase
        // This is a placeholder for future implementation
        throw new \Exception("DOCX rendering is not yet implemented");
    }

    /**
     * Generate filename for rendered document
     */
    private function generateFilename(DocumentType $type, string $extension, array $context): string
    {
        $timestamp = now()->format('YmdHis');
        $typeName = str_replace('_', '-', $type->value);
        
        // Try to get request number or other identifier from context
        $identifier = $context['request_number'] 
            ?? $context['request']?->request_number 
            ?? $context['process']?->sample?->testRequest?->request_number
            ?? 'PREVIEW';

        return "{$typeName}-{$identifier}-{$timestamp}.{$extension}";
    }
}
