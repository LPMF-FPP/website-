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
            
            // Trim email fields
            if (isset($notifications['email'])) {
                foreach (['default_recipient', 'subject', 'body'] as $field) {
                    if (isset($notifications['email'][$field])) {
                        $value = trim($notifications['email'][$field]);
                        $notifications['email'][$field] = $value === '' ? null : $value;
                    }
                }
            }
            
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

        // Normalize security roles
        if ($this->has('security.roles')) {
            $roles = $this->input('security.roles', []);
            
            // Trim role arrays
            foreach ($roles as $permission => $roleList) {
                if (is_array($roleList)) {
                    $roles[$permission] = array_map('trim', $roleList);
                }
            }
            
            $this->merge(['security' => ['roles' => $roles]]);
        }
    }

    public function rules(): array
    {
        return [
            // Support partial updates
            'notifications' => ['sometimes', 'required', 'array'],
            'notifications.email' => ['sometimes', 'required', 'array'],
            'notifications.email.enabled' => ['sometimes', 'boolean'],
            'notifications.email.default_recipient' => ['sometimes', 'nullable', 'email'],
            'notifications.email.subject' => ['sometimes', 'nullable', 'string', 'max:150'],
            'notifications.email.body' => ['sometimes', 'nullable', 'string'],
            
            'notifications.whatsapp' => ['sometimes', 'required', 'array'],
            'notifications.whatsapp.enabled' => ['sometimes', 'boolean'],
            'notifications.whatsapp.default_target' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notifications.whatsapp.message' => ['sometimes', 'nullable', 'string'],
            
            'security' => ['sometimes', 'required', 'array'],
            'security.roles' => ['sometimes', 'required', 'array'],
            'security.roles.can_manage_settings' => ['sometimes', 'required', 'array'],
            'security.roles.can_manage_settings.*' => ['string', 'max:50'],
            'security.roles.can_issue_number' => ['sometimes', 'required', 'array'],
            'security.roles.can_issue_number.*' => ['string', 'max:50'],
        ];
    }
}
