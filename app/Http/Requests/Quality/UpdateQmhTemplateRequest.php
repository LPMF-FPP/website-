<?php

namespace App\Http\Requests\Quality;

use App\Http\Requests\Quality\Concerns\HandlesQmhTemplateLayoutConfig;
use App\Support\QmhFormSchemaValidator;
use App\Support\QmhFrLayoutProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQmhTemplateRequest extends FormRequest
{
    use HandlesQmhTemplateLayoutConfig;

    private bool $schemaJsonDecodeFailed = false;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.template.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeRiskMatrixColumnsFromCsv();

        $raw = $this->input('form_schema_json');
        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->schemaJsonDecodeFailed = true;

            return;
        }

        if (! is_array($decoded)) {
            $this->schemaJsonDecodeFailed = true;

            return;
        }

        $this->merge([
            'form_schema' => $decoded,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'fr'])],
            'file' => ['nullable', 'file', 'mimes:docx', 'max:10240'],
            'content_html' => ['nullable', 'string'],
            'version_notes' => ['nullable', 'string', 'max:2000'],
            'form_schema_json' => ['nullable', 'string', 'max:50000'],
            'form_schema' => ['nullable', 'array'],
            'layout_profile' => ['nullable', Rule::in(QmhFrLayoutProfile::allowedProfiles())],
            'logo_source' => ['nullable', Rule::in(QmhFrLayoutProfile::allowedLogoSources())],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'declaration_header' => ['nullable', 'string', 'max:255'],
            'risk_matrix_columns_csv' => ['nullable', 'string', 'max:400'],
            'risk_matrix_columns' => ['nullable', 'array', 'min:2', 'max:6'],
            'risk_matrix_columns.*' => ['string', 'min:1', 'max:80'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->schemaJsonDecodeFailed) {
                $schema = $this->input('form_schema');
                if ($schema !== null) {
                    foreach (QmhFormSchemaValidator::errors($schema) as $message) {
                        $validator->errors()->add('form_schema_json', $message);
                    }
                }

                $this->validateLayoutConfig($validator);

                return;
            }

            $validator->errors()->add('form_schema_json', 'Schema pertanyaan harus berupa JSON valid.');
        });
    }
}
