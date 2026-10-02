<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConsolidatedReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('statistik.export');
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
            'calculation_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'narratives.opening' => ['nullable', 'string', 'max:5000'],
            'narratives.closing' => ['nullable', 'string', 'max:5000'],
            'signers' => ['required', 'array', 'size:3'],
            'signers.*.role' => ['required', 'in:Pembuat,Pemeriksa,Pengesah'],
            'signers.*.name' => ['required', 'string', 'max:100'],
            'signers.*.position' => ['required', 'string', 'max:100'],
            'signers.*.nip' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('period_type') !== 'quarterly' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::parse($this->input('period_start'));
            $end = Carbon::parse($this->input('period_end'));

            if (
                $start->toDateString() !== $start->copy()->startOfQuarter()->toDateString()
                || $end->toDateString() !== $start->copy()->endOfQuarter()->toDateString()
                || $start->year !== $end->year
            ) {
                $validator->errors()->add('period_start', 'Periode triwulan harus mencakup satu triwulan kalender penuh.');
            }

            if ($end->toDateString() >= now()->toDateString()) {
                $validator->errors()->add('period_end', 'Laporan triwulan resmi hanya dapat diterbitkan setelah triwulan berakhir.');
            }
        });
    }
}
