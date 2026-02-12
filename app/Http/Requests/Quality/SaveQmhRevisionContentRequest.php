<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class SaveQmhRevisionContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_html' => ['required', 'string'],
            'content_css' => ['nullable', 'string'],
            'editor_json' => ['nullable', 'array'],
        ];
    }
}
