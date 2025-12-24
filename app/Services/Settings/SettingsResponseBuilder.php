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
            'localization' => Arr::get($nested, 'locale', []),
            'retention' => $retention,
            'notifications' => Arr::get($nested, 'notifications', Arr::get($nested, 'automation', [])),
            'security' => Arr::get($nested, 'security.roles', []),
        ];
    }

    private function composeRetention(array $retention): array
    {
        $storagePath = Arr::get($retention, 'storage_folder_path', Arr::get($retention, 'base_path', ''));
        $retention['storage_folder_path'] = $storagePath;

        $disk = Arr::get($retention, 'storage_driver', 'local');
        $relative = trim($storagePath ?: Arr::get($retention, 'base_path', ''), '/');

        try {
            $retention['resolved_storage_path'] = rtrim(Storage::disk($disk)->path($relative), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        } catch (\Throwable $e) {
            $retention['resolved_storage_path'] = null;
        }

        return $retention;
    }
}
