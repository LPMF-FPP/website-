<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PdfRenderService
{
    /**
     * Convert HTML to PDF using Dompdf.
     *
     * @param string $html The HTML content to convert
     * @param string|null $baseUrl Base URL for resolving relative paths (optional)
     * @return string PDF binary content
     */
    public function htmlToPdf(string $html, ?string $baseUrl = null): string
    {
        try {
            $pdf = Pdf::loadHTML($this->applyBaseUrl($html, $baseUrl))
                ->setPaper('a4')
                ->setWarnings(false)
                ->setOption('dpi', 120)
                ->setOption('isRemoteEnabled', true)
                ->setOption('isHtml5ParserEnabled', true);

            $binary = $pdf->output();

            Log::info('PDF generated successfully via Dompdf', [
                'html_length' => strlen($html),
                'pdf_size' => strlen($binary),
            ]);

            return $binary;
        } catch (\Throwable $e) {
            Log::error('Dompdf failed to generate PDF', [
                'error' => $e->getMessage(),
                'html_length' => strlen($html),
            ]);

            throw new \RuntimeException("Failed to generate PDF: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Convert HTML to PDF with custom options.
     *
     * @param string $html The HTML content to convert
     * @param array $options Custom options for Dompdf
     * @return string PDF binary content
     */
    public function htmlToPdfWithOptions(string $html, array $options = []): string
    {
        try {
            $baseUrl = $options['base_url'] ?? null;
            $paper = $options['paper'] ?? 'a4';
            $orientation = $options['orientation'] ?? 'portrait';

            $pdf = Pdf::loadHTML($this->applyBaseUrl($html, $baseUrl))
                ->setPaper($paper, $orientation)
                ->setWarnings(false)
                ->setOption('dpi', $options['dpi'] ?? 120)
                ->setOption('isRemoteEnabled', $options['isRemoteEnabled'] ?? true)
                ->setOption('isHtml5ParserEnabled', true);

            return $pdf->output();
        } catch (\Throwable $e) {
            Log::error('PdfRenderService failed with custom options', [
                'error' => $e->getMessage(),
                'options' => $options,
            ]);

            throw new \RuntimeException("Failed to generate PDF: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Provide basic Dompdf configuration status for diagnostics.
     */
    public function testConfiguration(): array
    {
        $available = class_exists(\Dompdf\Dompdf::class);

        return [
            'success' => $available,
            'checks' => [
                'dompdf' => [
                    'available' => $available,
                    'default_paper' => config('dompdf.default_paper', 'a4'),
                    'dpi' => config('dompdf.dpi', 96),
                ],
            ],
        ];
    }

    private function applyBaseUrl(string $html, ?string $baseUrl): string
    {
        if (!$baseUrl) {
            return $html;
        }

        $baseTag = '<base href="' . rtrim($baseUrl, '/') . '/">';

        if (stripos($html, '<head') !== false) {
            return preg_replace('/<head(\s*[^>]*)>/i', '<head$1>' . $baseTag, $html, 1) ?? $html;
        }

        return '<!DOCTYPE html><html><head>' . $baseTag . '</head><body>' . $html . '</body></html>';
    }
}
