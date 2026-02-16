<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\QmhPreviewPdfRequest;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Services\Quality\QmhRevisionDownloadService;
use App\Support\QmhAnswerSanitizer;
use Illuminate\Http\Response;

class QmhPreviewController extends Controller
{
    public function pdf(QmhPreviewPdfRequest $request, QmhRevisionDownloadService $service): Response
    {
        $validated = $request->validated();

        $docTypeInput = (string) ($validated['doc_type'] ?? '');
        $docType = $docTypeInput === 'fr' ? 'formulir' : $docTypeInput;
        $templateDocType = match ($docTypeInput) {
            'formulir', 'fr' => 'fr',
            default => $docTypeInput,
        };

        $document = new QmhDocument([
            'doc_code' => (string) ($validated['doc_code'] ?? '-'),
            'title' => (string) ($validated['title'] ?? '-'),
            'clause' => (int) ($validated['clause'] ?? 4),
            'doc_type' => $docType,
            'parent_sop_id' => $validated['parent_sop_id'] ?? null,
            'paired_ik_id' => $validated['paired_ik_id'] ?? null,
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        if (! empty($validated['parent_sop_id'])) {
            $parent = QmhDocument::query()
                ->select(['id', 'doc_code', 'title', 'clause', 'doc_type'])
                ->whereKey((int) $validated['parent_sop_id'])
                ->first();

            if ($parent) {
                $document->setRelation('parentSop', $parent);
            }
        }

        if (! empty($validated['paired_ik_id'])) {
            $paired = QmhDocument::query()
                ->select(['id', 'doc_code', 'title', 'clause', 'doc_type', 'parent_sop_id'])
                ->whereKey((int) $validated['paired_ik_id'])
                ->first();

            if ($paired) {
                $document->setRelation('pairedIk', $paired);
            }
        }

        $template = null;
        if (! empty($validated['template_id'])) {
            $template = QmhTemplate::query()
                ->whereKey((int) $validated['template_id'])
                ->where('is_active', true)
                ->where('doc_type', $templateDocType)
                ->first();
        }

        $answers = QmhAnswerSanitizer::sanitizeAnswersJson($validated['answers_json'] ?? []);

        $templateMetadata = is_array($template?->metadata) ? $template->metadata : [];
        $schemaSnapshot = null;
        if ($template && is_array($templateMetadata['form_schema'] ?? null)) {
            $schemaSnapshot = $templateMetadata['form_schema'];
        }

        if (in_array($docType, ['formulir', 'fr'], true) && is_array($schemaSnapshot)) {
            foreach (['layout_profile', 'logo_source', 'logo_path', 'declaration_header', 'risk_matrix_columns'] as $key) {
                if (array_key_exists($key, $schemaSnapshot)) {
                    continue;
                }

                if (! array_key_exists($key, $templateMetadata)) {
                    continue;
                }

                $schemaSnapshot[$key] = $templateMetadata[$key];
            }
        }

        $revision = new QmhDocumentRevision([
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'template_id' => $template?->id,
            'template_name' => $template?->name,
            'template_version' => $template?->version,
            'change_summary' => $validated['change_summary'] ?? null,
            'answers_json' => $answers,
            'form_schema_json' => $schemaSnapshot,
            'effective_date' => null, // Auto-set on publish
            'content_html' => '<p></p>',
        ]);

        $revision->setRelation('document', $document);
        if ($template) {
            $revision->setRelation('template', $template);
        }

        $binary = $service->renderPdfBinary($revision, 'DRAFT PREVIEW', true);

        $safeDocCode = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $document->doc_code);
        $filename = ($safeDocCode ?: 'qmh-preview').'-preview.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
