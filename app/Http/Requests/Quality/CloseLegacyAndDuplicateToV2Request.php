<?php

declare(strict_types=1);

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class CloseLegacyAndDuplicateToV2Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'reason' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Idempotency key wajib diisi untuk aksi cutover.',
            'idempotency_key.regex' => 'Format idempotency key tidak valid.',
            'reason.required' => 'Alasan cutover wajib diisi.',
        ];
    }
}
