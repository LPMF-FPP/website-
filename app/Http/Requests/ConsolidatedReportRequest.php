<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsolidatedReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('statistics.export');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'period_type' => ['required', 'in:biweekly,monthly,quarterly'],
            'period_start' => ['required', 'date', 'before_or_equal:today'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:today'],
            'narratives.opening' => ['nullable', 'string', 'max:5000'],
            'narratives.closing' => ['nullable', 'string', 'max:5000'],
            'signers' => ['required', 'array', 'size:3'],
            'signers.*.role' => ['required', 'in:Pembuat,Pemeriksa,Pengesah'],
            'signers.*.name' => ['required', 'string', 'max:100'],
            'signers.*.position' => ['required', 'string', 'max:100'],
            'signers.*.nip' => ['nullable', 'string', 'max:30'],
        ];
    }
}
