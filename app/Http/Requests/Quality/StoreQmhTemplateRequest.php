<?php

namespace App\Http\Requests\Quality;

use App\Support\QmhFormSchemaValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQmhTemplateRequest extends FormRequest
{
    private bool $schemaJsonDecodeFailed = false;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.template.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
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
            'content_html' => ['required_without:file', 'nullable', 'string'],
            'version_notes' => ['nullable', 'string', 'max:2000'],
            'form_schema_json' => ['nullable', 'string', 'max:50000'],
            'form_schema' => ['nullable', 'array'],
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

                return;
            }

            $validator->errors()->add('form_schema_json', 'Schema pertanyaan harus berupa JSON valid.');
        });
    }
}
