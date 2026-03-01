<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhTemplate;
use App\Support\QmhHtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QmhTemplateController extends Controller
{
    public function preview(Request $request, QmhTemplate $template): View
    {
        $this->authorizePreviewAccess($request);

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $contentHtml = isset($metadata['content_html']) && is_string($metadata['content_html'])
            ? $metadata['content_html']
            : '<p></p>';
        $contentHtml = $this->normalizeTemplateContentHtml($contentHtml);

        return view('quality.templates.preview', [
            'template' => $template,
            'hasDocx' => false,
            'previewFileUrl' => null,
            'officeViewerUrl' => null,
            'contentHtml' => $contentHtml,
        ]);
    }

    private function normalizeTemplateContentHtml(string $contentHtml): string
    {
        $candidate = trim($contentHtml);
        if ($candidate === '') {
            return '<p></p>';
        }

        $decoded = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== $candidate) {
            $decodedHasRealTags = preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $decoded) === 1;
            $hasEncodedAngleBrackets = str_contains($candidate, '&lt;') || str_contains($candidate, '&gt;');

            if ($decodedHasRealTags && $hasEncodedAngleBrackets) {
                $candidate = $decoded;
            }
        }

        $sanitized = QmhHtmlSanitizer::sanitize($candidate);

        return trim($sanitized) === '' ? '<p></p>' : $sanitized;
    }

    private function authorizePreviewAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                $user->hasPermission('qmh.create')
                || $user->hasPermission('qmh.template.manage')
            ),
            403,
            'Anda tidak memiliki akses preview template.'
        );
    }
}
