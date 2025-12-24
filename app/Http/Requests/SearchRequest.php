<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $docTypes = config('search.doc_types', ['all']);

        return [
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'doc_type' => ['sometimes', 'string', 'in:' . implode(',', $docTypes)],
            'sort' => ['sometimes', 'string', 'in:relevance,latest,oldest'],
            'page_people' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'per_page_people' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'page_docs' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'per_page_docs' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }

    protected function passedValidation(): void
    {
        // After validation passes, prepare cleaned values
        $q = (string) $this->input('q', '');
        $q = trim($q);
        $q = preg_replace('/[[:cntrl:]]+/u', ' ', $q) ?? '';
        $q = preg_replace('/\s+/u', ' ', $q) ?? '';

        $this->merge([
            'q' => $q,
            'q_escaped' => $this->escapeLike($q),
        ]);
    }

    private function escapeLike(string $value, string $escapeChar = '\\'): string
    {
        return str_replace(
            [$escapeChar, '%', '_'],
            [$escapeChar . $escapeChar, $escapeChar . '%', $escapeChar . '_'],
            $value
        );
    }
}
