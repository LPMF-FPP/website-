<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class BrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Normalize branding fields
        if ($this->has('branding')) {
            $branding = $this->input('branding', []);
            
            // Trim string fields
            foreach (['lab_code', 'org_name', 'logo_path', 'primary_color', 'secondary_color', 'digital_stamp_path', 'watermark_path'] as $field) {
                if (isset($branding[$field])) {
                    $branding[$field] = is_string($branding[$field]) ? trim($branding[$field]) : $branding[$field];
                    // Convert empty string to null for nullable fields
                    if ($branding[$field] === '' && in_array($field, ['logo_path', 'secondary_color', 'digital_stamp_path', 'watermark_path'])) {
                        $branding[$field] = null;
                    }
                }
            }
            
            $this->merge(['branding' => $branding]);
        }

        // Normalize PDF fields
        if ($this->has('pdf')) {
            $pdf = $this->input('pdf', []);
            
            // Trim all string values recursively
            $pdf = $this->trimRecursive($pdf);
            
            $this->merge(['pdf' => $pdf]);
        }
    }

    /**
     * Recursively trim string values and convert empty strings to null.
     */
    private function trimRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->trimRecursive($value);
            } elseif (is_string($value)) {
                $trimmed = trim($value);
                $data[$key] = $trimmed === '' ? null : $trimmed;
            }
        }
        return $data;
    }

    public function rules(): array
    {
        $hexRule = ['sometimes', 'required', 'regex:/^#([a-f0-9]{6})$/i'];

        return [
            // Support partial updates with 'sometimes'
            'branding' => ['sometimes', 'required', 'array'],
            'branding.lab_code' => ['sometimes', 'required', 'string', 'max:20'],
            'branding.org_name' => ['sometimes', 'required', 'string', 'max:255'],
            'branding.logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'branding.primary_color' => $hexRule,
            'branding.secondary_color' => ['sometimes', 'nullable', 'regex:/^#([a-f0-9]{6})$/i'],
            'branding.digital_stamp_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'branding.watermark_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            
            'pdf' => ['sometimes', 'required', 'array'],
            'pdf.header' => ['sometimes', 'required', 'array'],
            'pdf.header.show' => ['sometimes', 'boolean'],
            'pdf.header.address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pdf.header.contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pdf.header.logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pdf.header.watermark' => ['sometimes', 'nullable', 'string', 'max:255'],
            
            'pdf.footer' => ['sometimes', 'required', 'array'],
            'pdf.footer.show' => ['sometimes', 'boolean'],
            'pdf.footer.text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pdf.footer.page_numbers' => ['sometimes', 'boolean'],
            
            'pdf.signature' => ['sometimes', 'required', 'array'],
            'pdf.signature.enabled' => ['sometimes', 'boolean'],
            'pdf.signature.signers' => ['sometimes', 'array'],
            'pdf.signature.signers.*.title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pdf.signature.signers.*.name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'pdf.signature.signers.*.stamp_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            
            'pdf.qr' => ['sometimes', 'required', 'array'],
            'pdf.qr.enabled' => ['sometimes', 'boolean'],
            'pdf.qr.target' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pdf.qr.caption' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
