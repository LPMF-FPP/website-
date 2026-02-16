<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+62|62|0)[0-9]{8,15}$/'],
            'title_prefix' => ['nullable', 'string', 'max:50'],
            'title_suffix' => ['nullable', 'string', 'max:50'],
            'rank' => ['nullable', 'string', 'max:100'],
            'nrp' => ['nullable', 'string', 'max:50'],
            'nip' => ['nullable', 'string', 'max:50'],
        ];
    }
}
