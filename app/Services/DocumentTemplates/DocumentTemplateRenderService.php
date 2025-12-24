<?php

namespace App\Services\DocumentTemplates;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Repositories\DocumentTemplateRepository;
use App\Services\DocumentGeneration\RenderedDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

class DocumentTemplateRenderService
{
    private array $allowedHosts = [];

    public function __construct(
        private readonly DocumentTemplateRepository $repository
    ) {
        $this->allowedHosts = $this->buildAllowedHosts();
    }

    public function renderHtml(
        DocumentTemplate $template,
        DocumentType $type,
        array $data = [],
        array $options = []
    ): RenderedDocument {
        $rawMarkup = $this->repository->getTemplateContent($template);

        $rendered = Blade::render($rawMarkup, $data);
        $sanitizedHtml = $this->sanitizeHtml($rendered);
        $sanitizedCss = $this->sanitizeCss($template->content_css ?? '');
        $html = $this->wrapHtmlDocument($sanitizedHtml, $sanitizedCss, $options);

        return new RenderedDocument(
            content: $html,
            mimeType: DocumentFormat::HTML->mimeType(),
            filename: $this->generateFilename($type, 'html'),
            format: DocumentFormat::HTML,
            templateId: $template->id,
            metadata: [
                'render_engine' => $template->render_engine?->value ?? DocumentRenderEngine::DOMPDF->value,
            ],
        );
    }

    public function renderPdf(
        DocumentTemplate $template,
        DocumentType $type,
        array $data = [],
        array $options = []
    ): RenderedDocument {
        $htmlDocument = $this->renderHtml($template, $type, $data, $options);
        $engine = DocumentRenderEngine::DOMPDF;
        $binary = $this->renderWithDompdf($htmlDocument->content, $options);

        return new RenderedDocument(
            content: $binary,
            mimeType: DocumentFormat::PDF->mimeType(),
            filename: $this->generateFilename($type, 'pdf'),
            format: DocumentFormat::PDF,
            templateId: $template->id,
            metadata: [
                'render_engine' => $engine->value,
            ],
        );
    }

    private function renderWithDompdf(string $html, array $options): string
    {
        $pdf = Pdf::loadHTML($html)
            ->setPaper($options['paper'] ?? 'a4')
            ->setWarnings(false)
            ->setOption('dpi', $options['dpi'] ?? 120)
            ->setOption('isRemoteEnabled', $options['isRemoteEnabled'] ?? false)
            ->setOption('isHtml5ParserEnabled', true);

        if (!empty($options['orientation']) && $options['orientation'] === 'landscape') {
            $pdf->setPaper('a4', 'landscape');
        }

        return $pdf->output();
    }

    private function wrapHtmlDocument(string $bodyHtml, string $css, array $options): string
    {
        $head = '<meta charset="utf-8">';
        $head .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
        $head .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $head .= '<style>body{font-family:Arial,Helvetica,sans-serif;color:#111;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{padding:4px;}</style>';

        if (!empty($css)) {
            $head .= "<style>{$css}</style>";
        }

        if (!empty($options['head_html'])) {
            $head .= $options['head_html'];
        }

        return '<!DOCTYPE html><html><head>' . $head . '</head><body>' . $bodyHtml . '</body></html>';
    }

    private function sanitizeHtml(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Remove script tags
        while (($scripts = $document->getElementsByTagName('script')) && $scripts->length > 0) {
            $scripts->item(0)?->parentNode?->removeChild($scripts->item(0));
        }

        $xpath = new DOMXPath($document);

        // Remove event handler attributes
        foreach ($xpath->query('//@*[starts-with(name(), "on")]') as $attribute) {
            $attribute->ownerElement?->removeAttributeNode($attribute);
        }

        // Re-map asset URLs
        foreach ($xpath->query('//@src | //@href') as $attribute) {
            $sanitized = $this->sanitizeAssetUrl($attribute->value);
            if ($sanitized === null) {
                $attribute->ownerElement?->removeAttributeNode($attribute);
            } else {
                $attribute->value = $sanitized;
            }
        }

        libxml_clear_errors();

        return $document->saveHTML();
    }

    private function sanitizeCss(?string $css): string
    {
        if (empty($css)) {
            return '';
        }

        $css = preg_replace('/@import\\s+url\\((https?:)?\\/\\/.+?\\);?/i', '', $css);
        $css = preg_replace('/expression\\s*\\(/i', '', $css);

        return trim((string) $css);
    }

    private function sanitizeAssetUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || Str::startsWith($value, 'javascript:')) {
            return null;
        }

        if (Str::startsWith($value, 'data:')) {
            return $value;
        }

        if (Str::startsWith($value, '//')) {
            $value = 'https:' . $value;
        }

        if (Str::startsWith(strtolower($value), 'http')) {
            $host = parse_url($value, PHP_URL_HOST);
            if ($host === null || !in_array($host, $this->allowedHosts, true)) {
                return null;
            }

            return $value;
        }

        $base = rtrim(config('app.url'), '/');

        if (Str::startsWith($value, '/')) {
            return $base . $value;
        }

        return $base . '/' . ltrim($value, './');
    }

    private function buildAllowedHosts(): array
    {
        $hosts = config('document-templates.allowed_asset_hosts', []);
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        if ($host) {
            $hosts[] = $host;
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private function generateFilename(DocumentType $type, string $extension): string
    {
        $timestamp = now()->format('YmdHis');
        $slug = Str::slug($type->value);

        return "{$slug}-preview-{$timestamp}.{$extension}";
    }
}
