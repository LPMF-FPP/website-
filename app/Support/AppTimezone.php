<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class AppTimezone
{
    public const CACHE_KEY = 'settings.localization.timezone';

    public static function current(): string
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            // Check for test overrides first
            if (isset($GLOBALS['__settings_overrides']['localization.timezone'])) {
                $tz = $GLOBALS['__settings_overrides']['localization.timezone'];
            } elseif (isset($GLOBALS['__settings_overrides']['locale.timezone'])) {
                $tz = $GLOBALS['__settings_overrides']['locale.timezone'];
            } else {
                // Bypass global settings() helper to avoid cache race conditions/stale data.
                // Direct query is safe because this method is already cached.
                try {
                    $row = \App\Models\SystemSetting::query()
                        ->whereIn('key', ['localization.timezone', 'locale.timezone'])
                        ->orderByRaw("CASE WHEN key = 'localization.timezone' THEN 1 ELSE 2 END")
                        ->first();
                    
                    $tz = $row?->value;
                } catch (\Throwable $e) {
                    // Fallback if table doesn't exist yet (e.g. fresh install)
                    $tz = null;
                }
            }

            $tz = is_string($tz) ? trim($tz) : null;

            if (! $tz || ! in_array($tz, timezone_identifiers_list(), true)) {
                $tz = config('app.timezone', 'UTC');
            }

            return $tz ?: 'UTC';
        });
    }

    public static function apply(?string $tz = null): string
    {
        $tz = $tz ?: self::current();

        if (! in_array($tz, timezone_identifiers_list(), true)) {
            $tz = config('app.timezone', 'UTC') ?: 'UTC';
        }

        config(['app.timezone' => $tz]);
        @date_default_timezone_set($tz);

        return $tz;
    }
}
