<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class UnlockQmhRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if (! $this->boolean('force')) {
            return $user->hasPermission('qmh.create');
        }

        return $user->hasPermission('qmh.unlock.force')
            || $user->hasPermission('qmh.force_unlock')
            || $user->hasPermission('qmh.template.manage')
            || (string) ($user->role ?? '') === 'admin';
    }

    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
            'reason' => ['required_if:force,1', 'nullable', 'string', 'max:255'],
        ];
    }
}
