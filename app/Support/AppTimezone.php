<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class AppTimezone
{
    public const CACHE_KEY = 'settings.localization.timezone';

    public static function current(): string
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $tz = settings('localization.timezone', settings('locale.timezone', config('app.timezone', 'UTC')));
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
