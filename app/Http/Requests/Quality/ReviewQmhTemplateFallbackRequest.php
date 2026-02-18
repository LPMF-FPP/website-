<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewQmhTemplateFallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.template.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'fallback_template_id' => ['required_if:action,approve', 'nullable', 'integer', 'exists:qmh_templates,id'],
            'note' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
        ];
    }
}
