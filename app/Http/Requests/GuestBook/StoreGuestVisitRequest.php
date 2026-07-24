<?php

declare(strict_types=1);

namespace App\Http\Requests\GuestBook;

use App\Enums\GuestVisitPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('guest-book.create') ?? false;
    }

    public function rules(): array
    {
        $isCasePurpose = in_array($this->input('purpose'), GuestVisitPurpose::casePurposes(), true);

        return [
            'investigator_id' => [
                Rule::requiredIf($isCasePurpose),
                'nullable',
                'exists:investigators,id',
            ],
            'visit_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:2024-01-01'],
            'visit_time' => ['required', 'date_format:H:i', 'after_or_equal:06:00', 'before_or_equal:22:00'],
            'purpose' => ['required', Rule::in(GuestVisitPurpose::all())],
            'purpose_detail' => [
                Rule::requiredIf(fn () => $this->input('purpose') === 'Lainnya'),
                'nullable', 'string', 'max:255',
            ],
            'host_id' => ['nullable', 'exists:users,id'],
            'same_as_owner' => ['boolean'],
            'visitor_name' => [
                Rule::requiredIf(! $isCasePurpose),
                Rule::requiredIf(fn () => $isCasePurpose && ! $this->boolean('same_as_owner')),
                'nullable', 'string', 'max:255',
            ],
            'visitor_identity' => [
                Rule::requiredIf(! $isCasePurpose),
                'nullable', 'string', 'max:50',
            ],
            'visitor_institution' => [
                Rule::requiredIf(! $isCasePurpose),
                'nullable', 'string', 'max:255',
            ],
            'visitor_relation' => [
                Rule::requiredIf(fn () => $isCasePurpose && ! $this->boolean('same_as_owner')),
                'nullable', 'string', 'max:50',
            ],
            'visitor_phone' => [
                Rule::requiredIf(! $isCasePurpose),
                'nullable', 'string', 'regex:/^[0-9+\- ]{8,20}$/',
            ],
            'nda_accepted' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'investigator_id.required' => 'Pemilik kasus wajib dipilih.',
            'investigator_id.exists' => 'Pemilik kasus tidak valid.',
            'visit_date.required' => 'Tanggal kunjungan wajib diisi.',
            'visit_date.after_or_equal' => 'Tanggal kunjungan tidak valid.',
            'visit_time.required' => 'Jam kunjungan wajib diisi.',
            'visit_time.after_or_equal' => 'Jam kunjungan minimal pukul 06:00.',
            'visit_time.before_or_equal' => 'Jam kunjungan maksimal pukul 22:00.',
            'purpose.required' => 'Keperluan wajib dipilih.',
            'purpose.in' => 'Keperluan tidak valid.',
            'purpose_detail.required' => 'Keperluan lainnya wajib diisi.',
            'visitor_name.required' => 'Nama tamu wajib diisi.',
            'visitor_identity.required' => 'Identitas tamu wajib diisi.',
            'visitor_institution.required' => 'Asal instansi wajib diisi.',
            'visitor_relation.required' => 'Relasi pihak yang datang wajib dipilih.',
            'visitor_phone.required' => 'Telepon tamu wajib diisi.',
            'visitor_phone.regex' => 'Format telepon tidak valid.',
            'nda_accepted.required' => 'Perjanjian kerahasiaan wajib disetujui.',
            'nda_accepted.accepted' => 'Perjanjian kerahasiaan wajib disetujui.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
        ];
    }
}
