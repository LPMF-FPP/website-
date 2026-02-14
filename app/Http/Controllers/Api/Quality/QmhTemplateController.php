<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhTemplate;
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

            $formSchema = $metadata['form_schema'] ?? $this->defaultFormSchema((string) $template->doc_type);

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
                'content_html' => $metadata['content_html'] ?? null,
                'form_schema' => $formSchema,
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
