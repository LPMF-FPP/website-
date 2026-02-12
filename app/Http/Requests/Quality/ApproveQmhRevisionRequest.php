<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveQmhRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'promote_to_new_edition' => ['nullable', 'boolean'],
            'reason' => [
                Rule::requiredIf($this->boolean('promote_to_new_edition')),
                'nullable',
                'string',
            ],
        ];
    }
}
