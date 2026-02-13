<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQmhTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.template.manage') ?? false;
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
            'version_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
