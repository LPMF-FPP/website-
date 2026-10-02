<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Services\IkuService;
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
            'period_mode' => ['sometimes', 'string', 'in:monthly,yearly,quarterly'],
            'weights' => ['sometimes', 'array:registration,lab_exam,report,survey'],
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
     * Validate the final saved weights, including untouched values in partial updates.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $weights = $this->input('weights');
            if (is_array($weights)) {
                $weightFields = ['weights.registration', 'weights.lab_exam', 'weights.report', 'weights.survey'];
                foreach ($weightFields as $field) {
                    if ($validator->errors()->has($field)) {
                        return;
                    }
                }

                $effectiveWeights = array_merge(app(IkuService::class)->getConfig()['weights'], $weights);
                $effectiveWeights = array_intersect_key(
                    $effectiveWeights,
                    array_fill_keys(['registration', 'lab_exam', 'report', 'survey'], true)
                );

                if (array_sum($effectiveWeights) !== 100) {
                    $validator->errors()->add('weights', 'Total bobot harus sama dengan 100%.');
                }
            }

            $targets = $this->input('target_samples_by_year');
            if (is_array($targets)) {
                foreach (array_keys($targets) as $year) {
                    if (! preg_match('/^\d{4}$/D', (string) $year) || (int) $year < 2020 || (int) $year > 2099) {
                        $validator->errors()->add('target_samples_by_year', 'Tahun target harus berada dalam rentang 2020 sampai 2099.');

                        return;
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'period_mode.in' => 'Mode periode harus monthly, quarterly atau yearly.',
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
