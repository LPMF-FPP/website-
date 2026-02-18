<?php

namespace App\Http\Requests\Quality;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SubmitQmhRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'reviewer_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $reviewerId = (int) $this->input('reviewer_id');
            if ($reviewerId <= 0) {
                return;
            }

            $reviewer = User::query()->find($reviewerId);
            if ($reviewer === null || ! $reviewer->is_active) {
                $validator->errors()->add('reviewer_id', 'Pemeriksa tidak aktif atau tidak ditemukan.');

                return;
            }

            if (! $reviewer->hasPermission('qmh.create')) {
                $validator->errors()->add('reviewer_id', 'Pemeriksa tidak memiliki otorisasi workflow QMH.');
            }
        });
    }
}
