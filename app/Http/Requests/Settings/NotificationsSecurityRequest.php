<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class NotificationsSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Normalize notifications fields
        if ($this->has('notifications')) {
            $notifications = $this->input('notifications', []);

            // Trim WhatsApp fields
            if (isset($notifications['whatsapp'])) {
                foreach (['default_target', 'message'] as $field) {
                    if (isset($notifications['whatsapp'][$field])) {
                        $value = trim($notifications['whatsapp'][$field]);
                        $notifications['whatsapp'][$field] = $value === '' ? null : $value;
                    }
                }
            }

            $this->merge(['notifications' => $notifications]);
        }
    }

    public function rules(): array
    {
        return [
            // Support partial updates
            'notifications' => ['sometimes', 'required', 'array'],

            // Allow email settings to pass through validation
            'notifications.email' => ['sometimes', 'array'],
            'notifications.email.enabled' => ['sometimes', 'boolean'],
            'notifications.email.default_recipient' => ['sometimes', 'nullable', 'email'],
            'notifications.email.subject' => ['sometimes', 'nullable', 'string'],
            'notifications.email.body' => ['sometimes', 'nullable', 'string'],

            'notifications.whatsapp' => ['sometimes', 'required', 'array'],
            'notifications.whatsapp.enabled' => ['sometimes', 'boolean'],
            'notifications.whatsapp.default_target' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notifications.whatsapp.message' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
