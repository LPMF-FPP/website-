<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocalizationSettingsRequest extends FormRequest
{
    public const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'];
    public const DATE_FORMATS = ['DD/MM/YYYY', 'YYYY-MM-DD', 'DD-MM-YYYY'];
    public const NUMBER_FORMATS = ['1.234,56', '1,234.56'];
    public const LANGUAGES = ['id', 'en'];

    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Normalize localization fields
        if ($this->has('localization')) {
            $localization = $this->input('localization', []);
            
            // Trim string fields
            if (isset($localization['timezone'])) {
                $localization['timezone'] = trim($localization['timezone']);
            }
            
            $this->merge(['localization' => $localization]);
        }

        // Normalize retention fields
        if ($this->has('retention')) {
            $retention = $this->input('retention', []);
            
            // Convert empty string to null for purge_after_days
            if (isset($retention['purge_after_days']) && $retention['purge_after_days'] === '') {
                $retention['purge_after_days'] = null;
            }
            
            // Trim storage_folder_path (only whitespace, NOT slashes yet - validation checks for leading slash)
            if (isset($retention['storage_folder_path'])) {
                $retention['storage_folder_path'] = trim($retention['storage_folder_path']);
            }
            
            // Trim export_filename_pattern
            if (isset($retention['export_filename_pattern'])) {
                $retention['export_filename_pattern'] = trim($retention['export_filename_pattern']);
            }
            
            $this->merge(['retention' => $retention]);
        }
    }

    public function rules(): array
    {
        return [
            // Localization rules - support partial updates
            'localization' => ['sometimes', 'required', 'array'],
            'localization.timezone' => ['sometimes', 'required', 'string', Rule::in(self::TIMEZONES)],
            'localization.date_format' => ['sometimes', 'required', 'string', Rule::in(self::DATE_FORMATS)],
            'localization.number_format' => ['sometimes', 'required', 'string', Rule::in(self::NUMBER_FORMATS)],
            'localization.language' => ['sometimes', 'required', 'string', Rule::in(self::LANGUAGES)],
            
            // Retention rules - support partial updates and nullable values
            'retention' => ['sometimes', 'required', 'array'],
            'retention.storage_driver' => ['sometimes', 'required', 'string', 'max:50'],
            'retention.storage_folder_path' => [
                'sometimes', 
                'string', 
                'max:255',
                // Prevent absolute paths and directory traversal
                'regex:/^[a-zA-Z0-9_\-\/]*$/',
                function ($attribute, $value, $fail) {
                    if (str_contains($value, '..')) {
                        $fail('Path tidak boleh mengandung ".." (directory traversal).');
                    }
                    if (str_starts_with($value, '/')) {
                        $fail('Path tidak boleh absolute (dimulai dengan "/").');
                    }
                },
            ],
            'retention.purge_after_days' => ['sometimes', 'nullable', 'integer', 'min:30', 'max:3650'],
            'retention.export_filename_pattern' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
