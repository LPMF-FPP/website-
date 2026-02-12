<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQmhDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_code' => ['required', 'string', 'max:100', 'unique:qmh_documents,doc_code'],
            'title' => ['required', 'string', 'max:255'],
            'clause' => ['required', 'integer', Rule::in([4, 5, 6, 7, 8])],
            'doc_type' => ['required', Rule::in(['sop', 'ik', 'formulir'])],
            'change_summary' => ['nullable', 'string'],
            'editor_json' => ['nullable', 'array'],
            'content_html' => ['nullable', 'string'],
            'content_css' => ['nullable', 'string'],
        ];
    }
}
