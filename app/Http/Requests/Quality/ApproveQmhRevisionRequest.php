<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveQmhRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('qmh.create') ?? false;
    }

    public function rules(): array
    {
        $checkerUnavailable = in_array((string) $this->input('checker_status'), ['unavailable', 'timeout'], true);

        return [
            'promote_to_new_edition' => ['nullable', 'boolean'],
            'reason' => [
                Rule::requiredIf($this->boolean('promote_to_new_edition')),
                'nullable',
                'string',
            ],
            'checker_status' => ['nullable', Rule::in(['pass', 'fail', 'unavailable', 'timeout'])],
            'checker_payload' => ['nullable', 'array'],
            'attestation_actor' => [
                Rule::requiredIf($checkerUnavailable),
                'nullable',
                'string',
                'max:255',
            ],
            'attestation_reason' => [
                Rule::requiredIf($checkerUnavailable),
                'nullable',
                'string',
                'max:2000',
            ],
            'incident_ref' => [
                Rule::requiredIf($checkerUnavailable),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $checkerStatus = (string) $this->input('checker_status', '');
            if (! in_array($checkerStatus, ['unavailable', 'timeout'], true)) {
                return;
            }

            $user = $this->user();
            $canAttest = $user?->hasPermission('qmh.approve.attest') ?? false;
            if (! $canAttest) {
                $validator->errors()->add('checker_status', 'Anda tidak memiliki izin attestation fallback untuk checker unavailable.');
            }
        });
    }
}
