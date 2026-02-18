<?php

namespace App\Http\Requests\Quality;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewQmhRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['return', 'pass'])],
            'note' => ['required_if:action,return', 'nullable', 'string'],
            'approver_id' => ['required_if:action,pass', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('action') !== 'pass') {
                return;
            }

            $approverId = (int) $this->input('approver_id');
            if ($approverId <= 0) {
                return;
            }

            $approver = User::query()->find($approverId);
            if ($approver === null || ! $approver->is_active) {
                $validator->errors()->add('approver_id', 'Pengesah tidak aktif atau tidak ditemukan.');

                return;
            }

            if (! $approver->hasPermission('qmh.create')) {
                $validator->errors()->add('approver_id', 'Pengesah tidak memiliki otorisasi workflow QMH.');
            }
        });
    }
}
