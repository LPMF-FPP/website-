<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class BackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'backup' => ['required', 'array'],
            'backup.retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
