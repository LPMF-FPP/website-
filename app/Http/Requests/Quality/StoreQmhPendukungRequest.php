<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQmhPendukungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_code' => ['required', 'string', 'max:100', 'regex:/^DP-[45678]\.\d{3}$/', 'unique:qmh_documents,doc_code'],
            'title' => ['required', 'string', 'max:255'],
            'clause' => ['required', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', $this->allowedMimes()),
                'max:'.$this->maxFileSizeKb(),
            ],
            'change_summary' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'doc_code.regex' => 'Format kode dokumen harus DP-{klausul}.###, contoh: DP-4.001',
            'doc_code.unique' => 'Kode dokumen sudah digunakan',
            'file.required' => 'File dokumen pendukung wajib diunggah.',
            'file.file' => 'File dokumen pendukung tidak valid.',
            'file.mimetypes' => 'Tipe file tidak diizinkan. Gunakan: jpg, png, webp, pdf',
            'file.max' => 'Ukuran file maksimal 30 MB',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedMimes(): array
    {
        $configured = config('quality.pendukung.allowed_mimes', []);
        if (! is_array($configured) || count($configured) === 0) {
            return ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        }

        return array_values(array_filter($configured, static fn ($item): bool => is_string($item) && $item !== ''));
    }

    private function maxFileSizeKb(): int
    {
        $configured = (int) config('quality.pendukung.max_file_size_kb', 30720);

        return $configured > 0 ? $configured : 30720;
    }
}
