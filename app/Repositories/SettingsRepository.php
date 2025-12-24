<?php

namespace App\Repositories;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;

/**
 * Repository for settings storage operations.
 * Provides abstraction over SystemSetting model for key-value storage.
 */
class SettingsRepository
{
    /**
     * Get all settings as key-value pairs.
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return SystemSetting::pluck('value', 'key')->toArray();
    }

    /**
     * Get a single setting value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Check if a setting key exists.
     */
    public function has(string $key): bool
    {
        return SystemSetting::where('key', $key)->exists();
    }

    /**
     * Store or update a setting.
     */
    public function put(string $key, mixed $value, ?int $userId = null): void
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by' => $userId,
            ]
        );
    }

    /**
     * Store or update multiple settings.
     *
     * @param  array<string,mixed>  $settings
     */
    public function putMany(array $settings, ?int $userId = null): void
    {
        foreach ($settings as $key => $value) {
            $this->put($key, $value, $userId);
        }
    }

    /**
     * Delete a setting by key.
     */
    public function forget(string $key): bool
    {
        return SystemSetting::where('key', $key)->delete() > 0;
    }

    /**
     * Delete multiple settings by keys.
     *
     * @param  array<string>  $keys
     */
    public function forgetMany(array $keys): int
    {
        return SystemSetting::whereIn('key', $keys)->delete();
    }

    /**
     * Get settings matching a key prefix.
     *
     * @return Collection<string,mixed>
     */
    public function prefix(string $prefix): Collection
    {
        return SystemSetting::where('key', 'like', $prefix.'%')
            ->pluck('value', 'key');
    }

    /**
     * Clear all settings (use with caution).
     */
    public function flush(): void
    {
        SystemSetting::truncate();
    }
}
