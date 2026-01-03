<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class IkuSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'period_mode' => ['sometimes', 'string', 'in:monthly,yearly'],
            'weights' => ['sometimes', 'array'],
            'weights.registration' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'weights.lab_exam' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'weights.report' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'weights.survey' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'target_samples_by_year' => ['sometimes', 'array'],
            'target_samples_by_year.*' => ['integer', 'min:1'],
            'sources' => ['sometimes', 'array'],
            'sources.A' => ['sometimes', 'string', 'in:requests_completed_count,lhu_issued_count'],
            'sources.B' => ['sometimes', 'string', 'in:requests_submitted_count'],
            'sources.C' => ['sometimes', 'string', 'in:samples_completed_count'],
            'sources.E' => ['sometimes', 'string', 'in:lhu_issued_count'],
            'survey_required_for_delivery' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Custom validation: weights must sum to 100 if all are provided.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $weights = $this->input('weights');
            if (is_array($weights)) {
                $sum = (int) ($weights['registration'] ?? 0)
                    + (int) ($weights['lab_exam'] ?? 0)
                    + (int) ($weights['report'] ?? 0)
                    + (int) ($weights['survey'] ?? 0);

                // Only validate sum if all weights are provided
                if (
                    isset($weights['registration'], $weights['lab_exam'], $weights['report'], $weights['survey'])
                    && $sum !== 100
                ) {
                    $validator->errors()->add('weights', 'Total bobot harus sama dengan 100%.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'period_mode.in' => 'Mode periode harus monthly atau yearly.',
            'weights.*.min' => 'Bobot tidak boleh negatif.',
            'weights.*.max' => 'Bobot tidak boleh lebih dari 100.',
            'target_samples_by_year.*.min' => 'Target sampel harus positif.',
            'sources.A.in' => 'Sumber A tidak valid.',
            'sources.B.in' => 'Sumber B tidak valid.',
            'sources.C.in' => 'Sumber C tidak valid.',
            'sources.E.in' => 'Sumber E tidak valid.',
        ];
    }
}
