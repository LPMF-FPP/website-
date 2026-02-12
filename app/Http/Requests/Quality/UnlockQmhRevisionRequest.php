<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class UnlockQmhRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
            'reason' => ['required_if:force,1', 'nullable', 'string', 'max:255'],
        ];
    }
}
