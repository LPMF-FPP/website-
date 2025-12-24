<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class NumberingPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', 'max:100'],
            'config' => ['nullable', 'array'],
        ];
    }
}
