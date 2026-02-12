<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadQmhRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'copy_type' => ['required', Rule::in(['controlled', 'uncontrolled'])],
            'reason' => [
                Rule::requiredIf($this->input('copy_type') === 'uncontrolled'),
                'nullable',
                'string',
            ],
            'distribution_target' => ['nullable', 'string', 'max:255'],
        ];
    }
}
