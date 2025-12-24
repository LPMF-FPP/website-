<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class NumberingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('numbering')) {
            $numbering = $this->input('numbering', []);
            
            // Normalize each numbering scope
            foreach ($numbering as $scope => $config) {
                // Convert empty string to null for nullable fields
                if (isset($config['reset']) && $config['reset'] === '') {
                    $numbering[$scope]['reset'] = null;
                }
                if (isset($config['start_from']) && $config['start_from'] === '') {
                    $numbering[$scope]['start_from'] = null;
                }
                // Trim pattern
                if (isset($config['pattern'])) {
                    $numbering[$scope]['pattern'] = trim($config['pattern']);
                }
            }
            
            $this->merge(['numbering' => $numbering]);
        }
    }

    public function rules(): array
    {
        return [
            'numbering' => ['sometimes', 'required', 'array', 'min:1'],
            'numbering.*.pattern' => ['sometimes', 'required', 'string', 'max:255'],
            'numbering.*.reset' => ['sometimes', 'nullable', 'string', 'in:daily,weekly,monthly,yearly,never'],
            'numbering.*.start_from' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'numbering.*.per_test_type' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
