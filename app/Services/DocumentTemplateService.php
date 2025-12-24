<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing document template operations
 * Provides template retrieval and token replacement for document generation
 */
class DocumentTemplateService
{
    /**
     * Get the active template for a given document type
     *
     * @param string $docType The document type (e.g., 'BA', 'LHU')
     * @return DocumentTemplate|null
     */
    public function getActiveTemplateByDocType(string $docType): ?DocumentTemplate
    {
        try {
            $template = DocumentTemplate::where('doc_type', $docType)
                ->where('is_active', true)
                ->where('status', 'issued')
                ->orderBy('version', 'desc')
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($template) {
                Log::info('Active template found', [
                    'doc_type' => $docType,
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'version' => $template->version,
                ]);
            } else {
                Log::warning('No active template found for doc_type', [
                    'doc_type' => $docType,
                ]);
            }

            return $template;
        } catch (\Exception $e) {
            Log::error('Error fetching active template', [
                'doc_type' => $docType,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Render HTML from template with real data using token replacement
     *
     * @param DocumentTemplate $template The template to render
     * @param array $data The real data for token replacement
     * @return string The rendered HTML
     */
    public function renderHtmlFromTemplate(DocumentTemplate $template, array $data): string
    {
        try {
            if (!empty($template->content_html)) {
                Log::debug('Using content_html for rendering');
                $html = $template->content_html;
            } else {
                Log::warning('Template has no content', [
                    'template_id' => $template->id,
                ]);
                $html = '<p>Template tidak memiliki konten.</p>';
            }

            // Apply token replacement
            $renderedHtml = $this->replaceTokens($html, $data);

            // Wrap with CSS if available
            if (!empty($template->content_css)) {
                $renderedHtml = $this->wrapWithCss($renderedHtml, $template->content_css);
            }

            return $renderedHtml;
        } catch (\Exception $e) {
            Log::error('Error rendering template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Replace tokens in HTML with actual data
     *
     * Token whitelist untuk keamanan - hanya token yang didefinisikan yang akan diganti
     *
     * @param string $html The HTML template
     * @param array $data The data for replacement
     * @return string HTML with replaced tokens
     */
    private function replaceTokens(string $html, array $data): string
    {
        // Define allowed tokens (whitelist)
        $allowedTokens = [
            // Request/General
            'request_number',
            'case_number',
            'to_office',
            'generated_at',
            
            // Investigator
            'investigator_name',
            'investigator_nrp',
            'investigator_rank',
            'investigator_jurisdiction',
            'investigator_phone',
            
            // Suspect
            'suspect_name',
            'suspect_gender',
            'suspect_age',
            'suspect_address',
            
            // Sample
            'sample_name',
            'sample_code',
            'sample_type',
            'sample_weight',
            'sample_count',
            'package_quantity',
            'packaging_type',
            
            // Test/Results
            'test_date',
            'test_methods',
            'analyst_name',
            'active_substance',
            'detected_substance',
            'test_result',
            'test_result_text',
            
            // LHU specific
            'lhu_number',
            'report_number',
            'instrument',
            'conclusion',
            
            // Lab info
            'lab_name',
            'lab_address',
        ];

        // Replace tokens
        foreach ($allowedTokens as $token) {
            $value = $this->getNestedValue($data, $token);
            
            if ($value !== null) {
                // Convert objects to string if possible
                if (is_object($value) && method_exists($value, '__toString')) {
                    $value = (string) $value;
                } elseif (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif (is_bool($value)) {
                    $value = $value ? 'Ya' : 'Tidak';
                }

                // Replace token
                $html = str_replace("{{{{$token}}}}", $value, $html);
            }
        }

        // Remove any remaining unreplaced tokens for safety
        $html = preg_replace('/\{\{[^}]+\}\}/', '', $html);

        return $html;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array $data
     * @param string $key
     * @return mixed|null
     */
    private function getNestedValue(array $data, string $key)
    {
        if (isset($data[$key])) {
            return $data[$key];
        }

        // Try dot notation (e.g., 'request.request_number')
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $data;

            foreach ($keys as $k) {
                if (is_array($value) && isset($value[$k])) {
                    $value = $value[$k];
                } elseif (is_object($value) && isset($value->$k)) {
                    $value = $value->$k;
                } else {
                    return null;
                }
            }

            return $value;
        }

        return null;
    }

    /**
     * Wrap HTML with CSS
     *
     * @param string $html
     * @param string $css
     * @return string
     */
    private function wrapWithCss(string $html, string $css): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        {$css}
    </style>
</head>
<body>
    {$html}
</body>
</html>
HTML;
    }

    /**
     * Calculate hash of template content for version tracking
     *
     * @param DocumentTemplate $template
     * @return string
     */
    public function calculateTemplateHash(DocumentTemplate $template): string
    {
        $content = json_encode([
            'html' => $template->content_html,
            'css' => $template->content_css,
        ]);

        return hash('sha256', $content);
    }
}
