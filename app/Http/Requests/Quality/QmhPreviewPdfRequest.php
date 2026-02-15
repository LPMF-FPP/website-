<?php

declare(strict_types=1);

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QmhPreviewPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'fr', 'formulir'])],
            'clause' => ['nullable', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'doc_code' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer', 'exists:qmh_templates,id'],
            'parent_sop_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'paired_ik_id' => ['nullable', 'integer', 'exists:qmh_documents,id'],
            'change_summary' => ['nullable', 'string'],
            'answers_json' => ['nullable', 'array'],
            'content_html' => ['nullable', 'string'],
            'dibuat_oleh' => ['nullable', 'integer', 'exists:users,id'],
            'diperiksa_oleh' => ['nullable', 'integer', 'exists:users,id'],
            'disahkan_oleh' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
