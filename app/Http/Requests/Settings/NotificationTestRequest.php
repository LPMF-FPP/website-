<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Trim target and message
        if ($this->has('target')) {
            $this->merge(['target' => trim($this->input('target'))]);
        }
        if ($this->has('message')) {
            $message = trim($this->input('message'));
            $this->merge(['message' => $message === '' ? null : $message]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
            'message' => ['nullable', 'string', 'max:1000'],
        ];

        // Dynamic validation for target based on channel
        if ($this->input('channel') === 'email') {
            $rules['target'] = ['required', 'email', 'max:255'];
        } elseif ($this->input('channel') === 'whatsapp') {
            $rules['target'] = [
                'required',
                'string',
                'max:50',
                'regex:/^(\+62|62|0)[0-9]{9,13}$/',
            ];
        } else {
            $rules['target'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'target.email' => 'Target harus berupa alamat email yang valid.',
            'target.regex' => 'Nomor WhatsApp harus dalam format Indonesia (+62xxx atau 08xxx).',
        ];
    }
}
