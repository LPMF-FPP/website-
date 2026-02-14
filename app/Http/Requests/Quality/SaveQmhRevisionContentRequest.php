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
            'content_html' => ['nullable', 'string'],
            'content_css' => ['nullable', 'string'],
            'editor_json' => ['nullable', 'array'],
            'answers_json' => ['nullable', 'array'],
            'effective_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasHtml = $this->has('content_html') && is_string($this->input('content_html'));
            $hasAnswers = $this->has('answers_json') && is_array($this->input('answers_json'));

            if ($hasHtml || $hasAnswers) {
                return;
            }

            $validator->errors()->add('content_html', 'Konten wajib diisi.');
        });
    }
}
