<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhTemplate;
use App\Support\QmhFrLayoutProfile;
use App\Support\QmhHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QmhTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'clause' => ['nullable', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'fr', 'formulir'])],
        ])->validate();

        $docType = $validated['doc_type'] === 'formulir' ? 'fr' : $validated['doc_type'];

        $templates = QmhTemplate::query()
            ->where('doc_type', $docType)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'clause',
                'doc_type',
                'version',
                'is_active',
                'source_docx_path',
                'metadata',
                'updated_at',
            ]);

        $templates = $templates->map(function (QmhTemplate $template): array {
            $metadata = is_array($template->metadata) ? $template->metadata : [];
            $isFr = ((string) $template->doc_type) === 'fr';

            $formSchema = $metadata['form_schema'] ?? $this->defaultFormSchema((string) $template->doc_type);
            $layoutConfig = $isFr
                ? QmhFrLayoutProfile::fromMetadata($metadata)
                : [
                    'layout_profile' => null,
                    'logo_source' => null,
                    'logo_path' => null,
                    'declaration_header' => null,
                    'risk_matrix_columns' => null,
                ];

            return [
                'id' => $template->id,
                'name' => $template->name,
                'clause' => $template->clause,
                'doc_type' => $template->doc_type,
                'version' => $template->version,
                'is_active' => $template->is_active,
                'source_docx_path' => $template->source_docx_path,
                'updated_at' => $template->updated_at,
                'preview_url' => route('quality.templates.preview', $template),
                'content_html' => QmhHtmlSanitizer::sanitize(is_string($metadata['content_html'] ?? null) ? $metadata['content_html'] : ''),
                'form_schema' => $formSchema,
                'layout_profile' => $layoutConfig['layout_profile'],
                'logo_source' => $layoutConfig['logo_source'],
                'logo_path' => $layoutConfig['logo_path'],
                'declaration_header' => $layoutConfig['declaration_header'],
                'risk_matrix_columns' => $layoutConfig['risk_matrix_columns'],
            ];
        })->values();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormSchema(string $docType): array
    {
        if ($docType === 'ik') {
            return [
                'version' => 1,
                'doc_type' => 'ik',
                'questions' => [
                    ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                    ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                    ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                    ['id' => 'reference', 'label' => 'Acuan', 'type' => 'textarea', 'required' => false],
                    ['id' => 'instructions', 'label' => 'Instruksi Kerja', 'type' => 'textarea', 'required' => true],
                    ['id' => 'required_docs', 'label' => 'Dokumentasi Yang Diperlukan', 'type' => 'list', 'required' => false],
                    ['id' => 'closing', 'label' => 'Penutup', 'type' => 'textarea', 'required' => false],
                ],
            ];
        }

        if ($docType !== 'sop') {
            return [
                'version' => 1,
                'doc_type' => $docType,
                'questions' => [],
            ];
        }

        return [
            'version' => 1,
            'doc_type' => 'sop',
            'questions' => [
                ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                ['id' => 'scope', 'label' => 'Ruang Lingkup', 'type' => 'textarea', 'required' => true],
                ['id' => 'definitions', 'label' => 'Definisi', 'type' => 'list', 'required' => false],
                ['id' => 'references', 'label' => 'Referensi', 'type' => 'list', 'required' => false],
                ['id' => 'procedure', 'label' => 'Prosedur', 'type' => 'textarea', 'required' => true],
                ['id' => 'records', 'label' => 'Rekaman / Form Terkait', 'type' => 'list', 'required' => false],
                ['id' => 'responsibilities', 'label' => 'Tanggung Jawab', 'type' => 'textarea', 'required' => false],
                ['id' => 'attachments', 'label' => 'Lampiran', 'type' => 'list', 'required' => false],
            ],
        ];
    }
}
