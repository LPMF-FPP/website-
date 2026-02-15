<?php

namespace App\Http\Requests\Quality;

use App\Models\QmhDocument;
use App\Models\QmhTemplate;
use App\Support\QmhFormAnswersValidator;
use App\Support\QmhFormSchemaValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQmhDocumentRequest extends FormRequest
{
    /**
     * @var array<string, string>
     */
    private array $answersErrors = [];

    /**
     * @var array<int, string>
     */
    private array $schemaErrors = [];

    private bool $schemaJsonDecodeFailed = false;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->normalizedTemplateDocType() !== 'fr') {
            return;
        }

        $template = $this->template();
        $metadata = is_array($template?->metadata) ? $template->metadata : [];

        $overrideSchema = $this->input('form_schema_json');
        if (is_string($overrideSchema) && trim($overrideSchema) !== '') {
            try {
                $decoded = json_decode($overrideSchema, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $this->schemaJsonDecodeFailed = true;
                $decoded = null;
            }

            if (is_array($decoded)) {
                $overrideSchema = $decoded;
            } else {
                $this->schemaJsonDecodeFailed = true;
                $overrideSchema = null;
            }
        }

        if (is_array($overrideSchema)) {
            $this->schemaErrors = QmhFormSchemaValidator::errors($overrideSchema);
        }

        $templateSchema = $metadata['form_schema'] ?? null;
        $resolvedSchema = is_array($overrideSchema)
            ? $overrideSchema
            : (is_array($templateSchema) ? $templateSchema : null);

        if (! is_array($resolvedSchema)) {
            return;
        }

        $result = QmhFormAnswersValidator::validateAndNormalize($resolvedSchema, $this->input('answers_json'));
        $this->answersErrors = $result['errors'];

        $this->merge([
            'form_schema_json' => $resolvedSchema,
            'answers_json' => $result['normalized'],
        ]);
    }

    public function rules(): array
    {
        return [
            'doc_code' => ['required', 'string', 'max:100', 'unique:qmh_documents,doc_code'],
            'title' => ['required', 'string', 'max:255'],
            'clause' => ['required', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'fr', 'formulir'])],
            'template_id' => [
                'required',
                'integer',
                Rule::exists('qmh_templates', 'id')->where(function ($query) {
                    $query
                        ->where('doc_type', $this->normalizedTemplateDocType())
                        ->where('is_active', true);
                }),
            ],
            'parent_sop_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'paired_ik_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'change_summary' => ['nullable', 'string'],
            'editor_json' => ['nullable', 'array'],
            'answers_json' => ['nullable', 'array'],
            'form_schema_json' => ['nullable', 'array'],
            'content_html' => ['nullable', 'string'],
            'content_css' => ['nullable', 'string'],
            'dibuat_oleh' => ['required', 'integer', 'exists:users,id'],
            'diperiksa_oleh' => ['nullable', 'integer', 'exists:users,id'],
            'disahkan_oleh' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $dibuat = (int) $this->input('dibuat_oleh');
            $diperiksa = (int) $this->input('diperiksa_oleh');
            $disahkan = (int) $this->input('disahkan_oleh');

            if ($dibuat && $diperiksa && $dibuat === $diperiksa) {
                $validator->errors()->add('diperiksa_oleh', 'Pemeriksa tidak boleh sama dengan Pembuat.');
            }

            if ($dibuat && $disahkan && $dibuat === $disahkan) {
                $validator->errors()->add('disahkan_oleh', 'Pengesah tidak boleh sama dengan Pembuat.');
            }

            if ($diperiksa && $disahkan && $diperiksa === $disahkan) {
                $validator->errors()->add('disahkan_oleh', 'Pengesah tidak boleh sama dengan Pemeriksa.');
            }

            $docType = $this->normalizedTemplateDocType();
            $parentSopId = $this->input('parent_sop_id');
            $pairedIkId = $this->input('paired_ik_id');

            if ($docType === 'sop') {
                if ($parentSopId !== null) {
                    $validator->errors()->add('parent_sop_id', 'SOP tidak boleh memiliki parent SOP.');
                }
                if ($pairedIkId !== null) {
                    $validator->errors()->add('paired_ik_id', 'SOP tidak boleh dipasangkan dengan IK.');
                }

                return;
            }

            if ($docType === 'ik') {
                if ($parentSopId === null) {
                    $validator->errors()->add('parent_sop_id', 'IK wajib memiliki parent SOP.');
                }

                if ($pairedIkId !== null) {
                    $validator->errors()->add('paired_ik_id', 'IK tidak boleh memiliki pasangan IK.');
                }
            }

            if ($docType === 'fr' && $parentSopId === null) {
                $validator->errors()->add('parent_sop_id', 'FR wajib memiliki parent SOP.');
            }

            $parentSop = null;
            if ($parentSopId !== null) {
                $parentSop = QmhDocument::query()->find((int) $parentSopId);
                if ($parentSop === null || $parentSop->doc_type !== 'sop') {
                    $validator->errors()->add('parent_sop_id', 'Parent dokumen harus berupa SOP.');
                }

                if ($parentSop !== null && (int) $parentSop->clause !== (int) $this->input('clause')) {
                    $validator->errors()->add('parent_sop_id', 'Parent SOP harus berada pada klausul yang sama.');
                }
            }

            if ($pairedIkId !== null) {
                if ($docType !== 'fr') {
                    $validator->errors()->add('paired_ik_id', 'Hanya FR yang boleh dipasangkan ke IK.');

                    return;
                }

                $pairedIk = QmhDocument::query()->find((int) $pairedIkId);
                if ($pairedIk === null || $pairedIk->doc_type !== 'ik') {
                    $validator->errors()->add('paired_ik_id', 'Pasangan wajib berupa dokumen IK.');

                    return;
                }

                if ($parentSop !== null && (int) $pairedIk->parent_sop_id !== (int) $parentSop->id) {
                    $validator->errors()->add('paired_ik_id', 'FR dan IK pasangan wajib berada pada parent SOP yang sama.');
                }
            }

            foreach ($this->answersErrors as $key => $message) {
                $validator->errors()->add($key, $message);
            }

            if ($this->schemaJsonDecodeFailed) {
                $validator->errors()->add('form_schema_json', 'Schema pertanyaan harus berupa JSON valid.');

                return;
            }

            foreach ($this->schemaErrors as $message) {
                $validator->errors()->add('form_schema_json', $message);
            }
        });
    }

    public function template(): ?QmhTemplate
    {
        $templateId = $this->integer('template_id');
        if ($templateId <= 0) {
            return null;
        }

        return QmhTemplate::query()->find($templateId);
    }

    private function normalizedTemplateDocType(): string
    {
        return match ($this->input('doc_type')) {
            'formulir', 'fr' => 'fr',
            default => (string) $this->input('doc_type'),
        };
    }
}
