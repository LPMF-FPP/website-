<?php

declare(strict_types=1);

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQmhPreviewArtifactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::in(['fr', 'formulir'])],
            'source_pdf_file' => [
                'required',
                'file',
                'mimetypes:application/pdf',
                'max:'.$this->maxFrV2PdfSizeKb(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'source_pdf_file.required' => 'File PDF sumber wajib diunggah untuk preview FR-v2.',
            'source_pdf_file.mimetypes' => 'File sumber preview harus berformat PDF.',
            'source_pdf_file.max' => 'Ukuran file PDF sumber preview melebihi batas maksimum.',
        ];
    }

    private function maxFrV2PdfSizeKb(): int
    {
        $configured = (int) config('quality.fr_v2.max_pdf_size_kb', 10240);

        return $configured > 0 ? $configured : 10240;
    }
}
