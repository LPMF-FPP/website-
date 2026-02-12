<?php

namespace App\Http\Requests\Quality;

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
}
