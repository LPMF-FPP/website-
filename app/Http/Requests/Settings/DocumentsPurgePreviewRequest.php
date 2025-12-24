<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentsPurgePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('filters') || $this->input('filters') === null) {
            $this->merge(['filters' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['uploaded_only', 'generated_only', 'all_documents'])],
            'filters' => ['nullable', 'array'],
            'filters.document_types' => ['nullable', 'array'],
            'filters.document_types.*' => ['string', 'max:100'],
            'filters.statuses' => ['nullable', 'array'],
            'filters.statuses.*' => ['string', 'max:100'],
            'filters.test_request_ids' => ['nullable', 'array'],
            'filters.test_request_ids.*' => ['integer', 'exists:test_requests,id'],
            'filters.investigator_ids' => ['nullable', 'array'],
            'filters.investigator_ids.*' => ['integer', 'exists:investigators,id'],
            'filters.request_numbers' => ['nullable', 'array'],
            'filters.request_numbers.*' => ['string', 'max:100'],
            'filters.created_from' => ['nullable', 'date'],
            'filters.created_until' => ['nullable', 'date', 'after_or_equal:filters.created_from'],
            'filters.generated_from' => ['nullable', 'date'],
            'filters.generated_until' => ['nullable', 'date', 'after_or_equal:filters.generated_from'],
            'include_orphans' => ['sometimes', 'boolean'],
        ];
    }
}
