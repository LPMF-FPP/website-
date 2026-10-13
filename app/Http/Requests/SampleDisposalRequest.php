<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SampleDisposalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('sample_ids_json')) {
            $raw = $this->input('sample_ids_json');
            $ids = is_string($raw) ? json_decode($raw, true) : null;
            $this->merge(['sample_ids' => ! $this->has('sample_ids') && is_array($ids) && array_is_list($ids) ? $ids : null]);
        }
    }

    public function authorize(): bool
    {
        return ($this->user()?->hasPermission('sample-disposal.view') ?? false)
            && ($this->user()?->hasPermission('sample-disposal.execute') ?? false);
    }

    public static function executionRules(): array
    {
        return [
            'sample_ids' => ['required', 'array', 'min:1'],
            'sample_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'review_token' => ['required', 'string'],
            'review_confirmed' => ['required', 'accepted'],
            'method' => ['required', 'in:bakar,kubur,hancur,lainnya'],
            'executor_name' => ['nullable', 'string', 'max:255'],
            'executor_role' => ['nullable', 'string', 'max:255'],
            'executor_identity' => ['nullable', 'string', 'max:255'],
            'witnesses' => ['required', 'array', 'list', 'min:1'],
            'witnesses.*' => ['array'],
            'witnesses.*.user_id' => ['nullable', 'integer', 'min:1'],
            'witnesses.*.name' => ['nullable', 'string', 'max:255'],
            'witnesses.*.role' => ['nullable', 'string', 'max:255'],
            'witnesses.*.identity' => ['nullable', 'string', 'max:255'],
            'approver_name' => ['required', 'string', 'max:255'],
            'approver_role' => ['required', 'string', 'max:255'],
            'approver_identity' => ['nullable', 'string', 'max:255'],
            'documentation_photos' => ['nullable', 'array', 'max:5'],
            'documentation_photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function rules(): array
    {
        return self::executionRules();
    }

    public function messages(): array
    {
        return [
            'approver_name.required' => 'Nama approver wajib diisi.',
            'approver_role.required' => 'Pangkat atau jabatan approver wajib diisi.',
            'review_token.required' => 'Buka kembali preview batch sebelum eksekusi.',
            'review_confirmed.accepted' => 'Tinjau dan konfirmasi manifest serta bukti eksekusi.',
            'review_confirmed.required' => 'Konfirmasi hasil review sebelum eksekusi.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(redirect()->back()->withErrors($validator)
            ->withInput($this->except(['documentation_photos', 'review_token', '_token'])));
    }
}
