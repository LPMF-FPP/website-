<?php

declare(strict_types=1);

namespace App\Http\Requests\GuestBook;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('guest-book.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'same_as_owner' => ['boolean'],
            'visitor_name' => ['required_unless:same_as_owner,1', 'nullable', 'string', 'max:255'],
            'visitor_identity' => ['nullable', 'string', 'max:50'],
            'visitor_relation' => ['required_without:same_as_owner', 'nullable', 'string', 'max:50'],
            'visitor_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'visitor_name.required_unless' => 'Nama pihak yang datang wajib diisi.',
            'visitor_relation.required_without' => 'Relasi pihak yang datang wajib dipilih.',
        ];
    }
}
