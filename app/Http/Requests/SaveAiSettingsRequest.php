<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') === true && $this->user()?->email_verified_at !== null;
    }

    public function rules(): array
    {
        return [
            'ai_provider' => 'required|string|in:openai,openrouter,deepseek,custom',
            'ai_base_url' => 'nullable|string|max:2048',
            'ai_model' => 'nullable|string|max:120|regex:/^[A-Za-z0-9_.:\/-]+$/',
            'ai_api_key' => 'nullable|string|max:500',
            'ai_reset_overrides' => 'sometimes|boolean',
            'ai_remove_key' => 'sometimes|boolean',
            'ai_confirm_destination' => 'sometimes|boolean',
            'expected_revision' => 'sometimes|string|max:64',
        ];
    }
}
