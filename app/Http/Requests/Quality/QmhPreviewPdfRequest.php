<?php

declare(strict_types=1);

namespace App\Http\Requests\Quality;

use App\Support\QmhFormSchemaValidator;
use App\Support\QmhFrV2Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QmhPreviewPdfRequest extends FormRequest
{
    private bool $schemaJsonDecodeFailed = false;

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isFrV2CreatePreviewMode()) {
            $mode = is_string($this->input('fr_v2_structure_mode'))
                ? strtolower(trim((string) $this->input('fr_v2_structure_mode')))
                : '';

            $this->merge([
                'fr_v2_structure_mode' => in_array($mode, ['table', 'non_table'], true)
                    ? $mode
                    : 'non_table',
            ]);
        }

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
            'form_schema_json' => $decoded,
        ]);
    }

    public function rules(): array
    {
        $sourcePdfFileRules = [
            'nullable',
            'file',
            'mimetypes:application/pdf',
            'max:'.$this->maxFrV2PdfSizeKb(),
        ];

        $sourcePdfTokenRules = [
            'nullable',
            'string',
            'max:120',
        ];

        if ($this->isFrV2CreatePreviewMode()) {
            array_unshift($sourcePdfFileRules, 'required_without:source_pdf_token');
            array_unshift($sourcePdfTokenRules, 'required_without:source_pdf_file');
        }

        return [
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'fr', 'formulir'])],
            'clause' => ['nullable', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'doc_code' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer', 'exists:qmh_templates,id'],
            'fr_v2_structure_mode' => ['nullable', 'string', Rule::in(['table', 'non_table'])],
            'parent_sop_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'paired_ik_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'change_summary' => ['nullable', 'string'],
            'answers_json' => [
                'nullable',
                'array',
                Rule::prohibitedIf(fn (): bool => $this->isFrV2CreatePreviewMode()),
            ],
            'form_schema_json' => [
                'nullable',
                'array',
                Rule::prohibitedIf(fn (): bool => $this->isFrV2CreatePreviewMode()),
            ],
            'content_html' => ['nullable', 'string'],
            'source_pdf_file' => $sourcePdfFileRules,
            'source_pdf_token' => $sourcePdfTokenRules,
            'dibuat_oleh' => ['nullable', 'integer', 'exists:users,id'],
            'diperiksa_oleh' => ['nullable', 'integer', 'exists:users,id'],
            'disahkan_oleh' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_pdf_file.required' => 'Preview FR-v2 membutuhkan file PDF sumber.',
            'source_pdf_file.required_without' => 'Preview FR-v2 membutuhkan file PDF sumber atau token artefak preview.',
            'source_pdf_file.mimetypes' => 'File sumber preview harus berformat PDF.',
            'source_pdf_file.max' => 'Ukuran file PDF sumber preview melebihi batas maksimum.',
            'source_pdf_token.required_without' => 'Token artefak preview diperlukan jika file PDF tidak dikirim ulang.',
            'answers_json.prohibited' => 'Payload jawaban legacy tidak didukung untuk preview FR-v2.',
            'form_schema_json.prohibited' => 'Payload schema legacy tidak didukung untuk preview FR-v2.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->schemaJsonDecodeFailed) {
                $validator->errors()->add('form_schema_json', 'Schema pertanyaan harus berupa JSON valid.');

                return;
            }

            if ($this->isFrV2CreatePreviewMode()) {
                return;
            }

            $docType = (string) $this->input('doc_type', '');
            $schema = $this->input('form_schema_json');
            if (! is_array($schema)) {
                return;
            }

            if (! in_array($docType, ['fr', 'formulir'], true)) {
                $validator->errors()->add('form_schema_json', 'Schema pertanyaan hanya dapat digunakan untuk dokumen Formulir (FR).');

                return;
            }

            foreach (QmhFormSchemaValidator::errors($schema) as $message) {
                $validator->errors()->add('form_schema_json', $message);
            }
        });
    }

    private function isFrV2PreviewMode(): bool
    {
        return QmhFrV2Gate::isCreateEnabled((string) $this->input('doc_type'));
    }

    private function isFrV2CreatePreviewMode(): bool
    {
        return $this->isFrV2PreviewMode() && $this->route('revision') === null;
    }

    private function maxFrV2PdfSizeKb(): int
    {
        $configured = (int) config('quality.fr_v2.max_pdf_size_kb', 10240);

        return $configured > 0 ? $configured : 10240;
    }
}
