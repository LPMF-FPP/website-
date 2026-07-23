<?php

namespace App\Http\Requests\GuestBook;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuestVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('guest-book.edit') ?? false;
    }

    public function rules(): array
    {
        $purposeOptions = [
            'Permohonan Pengujian',
            'Pengambilan Hasil Pengujian',
            'Audit Mutu',
            'Inspeksi',
            'Lainnya',
        ];

        return [
            'visit_date' => ['required', 'date', 'before_or_equal:today'],
            'visit_time' => ['required', 'date_format:H:i'],
            'purpose' => ['required', Rule::in($purposeOptions)],
            'host_id' => ['nullable', 'exists:users,id'],
            'same_as_owner' => ['boolean'],
            'visitor_name' => ['required_unless:same_as_owner,1', 'nullable', 'string', 'max:255'],
            'visitor_identity' => ['nullable', 'string', 'max:50'],
            'visitor_relation' => ['required_without:same_as_owner', 'nullable', 'string', 'max:50'],
            'visitor_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'visit_date.required' => 'Tanggal kunjungan wajib diisi.',
            'visit_time.required' => 'Jam kunjungan wajib diisi.',
            'purpose.required' => 'Keperluan wajib dipilih.',
            'visitor_name.required_unless' => 'Nama pihak yang datang wajib diisi.',
            'visitor_relation.required_without' => 'Relasi pihak yang datang wajib dipilih.',
        ];
    }
}
