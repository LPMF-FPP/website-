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
        $payload = $this->all();

        if (! empty($payload['settings']) && is_array($payload['settings'])) {
            $payload = array_merge($payload, $payload['settings']);
            unset($payload['settings']);
        }

        if (isset($payload['locale']) && ! isset($payload['localization'])) {
            $payload['localization'] = $payload['locale'];
        }

        if (isset($payload['localization_retention']) && is_array($payload['localization_retention'])) {
            $payload = array_merge($payload, $payload['localization_retention']);
            unset($payload['localization_retention']);
        }

        $this->replace($payload);

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

    public static function timezones(): array
    {
        $zones = timezone_identifiers_list();
        if (! is_array($zones)) {
            $zones = [];
        }

        return array_values(array_unique(array_merge(self::TIMEZONES, $zones)));
    }

    public function rules(): array
    {
        return [
            // Localization rules - support partial updates
            'localization' => ['sometimes', 'array'],
            'localization.timezone' => ['sometimes', 'required', 'string', function ($attribute, $value, $fail) {
                if (! in_array($value, timezone_identifiers_list(), true)) {
                    $fail('Invalid timezone');
                }
            }],
            'localization.language' => ['sometimes', 'required', 'string', Rule::in(self::LANGUAGES)],

            // Retention rules - support partial updates and nullable values
            'retention' => ['sometimes', 'array'],
            'retention.storage_driver' => ['sometimes', 'required', 'string', Rule::in(['public'])],
            // storage_folder_path deprecated - path otomatis berdasarkan investigator/request
            'retention.storage_folder_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'retention.purge_after_days' => ['sometimes', 'nullable', 'integer', 'min:30', 'max:3650'],
            'retention.export_filename_pattern' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'localization.timezone.required' => 'Timezone wajib diisi.',
            'localization.timezone.string' => 'Timezone harus berupa string.',
            'localization.language.in' => 'Bahasa tidak valid.',
            'retention.storage_driver.in' => 'Storage driver tidak valid.',
        ];
    }
}
