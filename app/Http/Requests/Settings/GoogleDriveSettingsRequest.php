<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoogleDriveSettingsRequest extends FormRequest
{
    public const REQUEST_FOLDER_MODES = [
        'request_number',
        'request_number_suspect',
        'suspect_request_number',
        'month_suspect',
    ];

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

        if (isset($payload['google_drive']) && is_array($payload['google_drive'])) {
            $googleDrive = $payload['google_drive'];

            foreach (['folder_id', 'uploads_folder_name', 'request_folder_mode', 'uploader_user_id'] as $key) {
                if (isset($googleDrive[$key]) && is_string($googleDrive[$key])) {
                    $googleDrive[$key] = trim($googleDrive[$key]);
                }
            }

            if (($googleDrive['uploader_user_id'] ?? '') === '') {
                $googleDrive['uploader_user_id'] = null;
            }

            $googleDrive['use_suspect_name'] = filter_var($googleDrive['use_suspect_name'] ?? false, FILTER_VALIDATE_BOOL);
            $payload['google_drive'] = $googleDrive;
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        return [
            'google_drive' => ['required', 'array'],
            'google_drive.folder_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'google_drive.uploads_folder_name' => ['required', 'string', 'max:120'],
            'google_drive.request_folder_mode' => ['required', 'string', Rule::in(self::REQUEST_FOLDER_MODES)],
            'google_drive.use_suspect_name' => ['required', 'boolean'],
            'google_drive.uploader_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'google_drive.folder_id.regex' => 'Folder ID Google Drive hanya boleh berisi huruf, angka, underscore, dan dash.',
            'google_drive.uploads_folder_name.required' => 'Nama folder utama wajib diisi.',
            'google_drive.request_folder_mode.in' => 'Mode nama folder permintaan tidak valid.',
        ];
    }
}
