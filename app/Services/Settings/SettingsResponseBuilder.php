<?php

namespace App\Services\Settings;

use App\Models\DocumentTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SettingsResponseBuilder
{
    public function build(): array
    {
        $flat = settings();
        $nested = settings_nest($flat);

        $retention = $this->composeRetention(Arr::get($nested, 'retention', []));

        return [
            'numbering' => Arr::get($nested, 'numbering', []),
            'templates' => [
                'active' => Arr::get($nested, 'templates.active', []),
                'list' => DocumentTemplate::orderBy('name')->get(),
            ],
            'branding' => Arr::get($nested, 'branding', []),
            'pdf' => Arr::get($nested, 'pdf', []),
            'localization' => Arr::get($nested, 'localization', Arr::get($nested, 'locale', [])),
            'retention' => $retention,
            'notifications' => Arr::get($nested, 'notifications', Arr::get($nested, 'automation', [])),
            'smtp' => $this->composeSmtp(Arr::get($nested, 'smtp', [])),
            'security' => Arr::get($nested, 'security.roles', []),
        ];
    }

    private function composeSmtp(array $smtp): array
    {
        // Return SMTP config without exposing password
        return [
            'host' => Arr::get($smtp, 'host', '127.0.0.1'),
            'port' => (int) Arr::get($smtp, 'port', 1025),
            'username' => Arr::get($smtp, 'username', ''),
            'password' => Arr::get($smtp, 'password') ? '••••••••' : '', // Mask password
            'from_address' => Arr::get($smtp, 'from_address', ''),
            'from_name' => Arr::get($smtp, 'from_name', 'LPMF LIMS'),
        ];
    }

    private function composeRetention(array $retention): array
    {
        $storagePath = Arr::get($retention, 'storage_folder_path', Arr::get($retention, 'base_path', ''));
        $retention['storage_folder_path'] = $storagePath;

        $disk = Arr::get($retention, 'storage_driver', 'public');
        $relative = trim($storagePath ?: Arr::get($retention, 'base_path', ''), '/');

        try {
            $retention['resolved_storage_path'] = rtrim(Storage::disk($disk)->path($relative), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        } catch (\Throwable $e) {
            $retention['resolved_storage_path'] = null;
        }

        return $retention;
    }
}
