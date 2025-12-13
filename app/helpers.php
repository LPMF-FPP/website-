<?php

use App\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

// Internal override store for tests / runtime fakes (not cached)
if (!isset($GLOBALS['__settings_overrides'])) {
    $GLOBALS['__settings_overrides'] = [];
}

if (!function_exists('settings')) {
    /**
     * Retrieve cached system settings, optionally for a dot-notated key.
     * Supports runtime overrides via settings_fake().
     *
     * @param  string|null  $key
     * @param  mixed  $default
     */
    function settings(?string $key = null, $default = null)
    {
        $all = cache()->remember('sys_settings_all', 60, function () {
            if (!Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::query()
                ->get()
                ->mapWithKeys(fn (SystemSetting $row) => [$row->key => $row->value])
                ->toArray();
        });

        // Merge in overrides (not persisted / not cached) – overrides take precedence
        if (!empty($GLOBALS['__settings_overrides'])) {
            $all = array_merge($all, $GLOBALS['__settings_overrides']);
        }

        if ($key) {
            // Check for exact flat key match first (for test fakes)
            if (array_key_exists($key, $all)) {
                return $all[$key];
            }
            // Otherwise use dot notation
            return data_get($all, $key, $default);
        }

        return $all;
    }
}

if (!function_exists('settings_fake')) {
    /**
     * Inject fake settings for tests without touching DB. Values override cached values.
     * Pass $replace=true to clear previous fakes.
     *
     * @param array<string,mixed> $pairs
     */
    function settings_fake(array $pairs, bool $replace = false): void
    {
        if ($replace) {
            $GLOBALS['__settings_overrides'] = [];
        }
        $GLOBALS['__settings_overrides'] = array_merge($GLOBALS['__settings_overrides'], $pairs);
    }
}

if (!function_exists('settings_fake_clear')) {
    function settings_fake_clear(): void
    {
        $GLOBALS['__settings_overrides'] = [];
    }
}

if (!function_exists('settings_forget_cache')) {
    function settings_forget_cache(): void
    {
        cache()->forget('sys_settings_all');
    }
}

if (!function_exists('settings_nest')) {
    /**
     * Transform a flat dot-notated array into nested arrays.
     *
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    function settings_nest(array $flat): array
    {
        $nested = [];
        foreach ($flat as $key => $value) {
            data_set($nested, $key, $value);
        }

        return $nested;
    }
}

if (!function_exists('settings_flatten')) {
    /**
     * Flatten nested arrays into dot-notated keys.
     *
     * @param  array<string, mixed>  $nested
     * @return array<string, mixed>
     */
    function settings_flatten(array $nested): array
    {
        return Arr::dot($nested);
    }
}

if (!function_exists('settings_flush_cache')) {
    /**
     * Backwards compatible alias for settings cache invalidation.
     */
    function settings_flush_cache(): void
    {
        settings_forget_cache();
    }
}

// Localization formatting helpers
if (!function_exists('fmt_date')) {
    function fmt_date($dt, $format = null)
    {
        if ($dt === null || $dt === '') {
            return '';
        }
        $format = $format ?? settings('locale.date_format', 'DD/MM/YYYY');
        $carbon = \Carbon\Carbon::parse($dt);
        $map = [
            'DD/MM/YYYY' => 'd/m/Y',
            'YYYY-MM-DD' => 'Y-m-d',
            'DD-MM-YYYY' => 'd-m-Y',
            // fallback to PHP style if already using PHP tokens
            'd/m/Y' => 'd/m/Y',
            'Y-m-d' => 'Y-m-d',
            'd-m-Y' => 'd-m-Y',
        ];
        return $carbon->format($map[$format] ?? 'd/m/Y');
    }
}

if (!function_exists('fmt_number')) {
    function fmt_number($num, int $decimals = 2)
    {
        if ($num === null || $num === '') {
            return '';
        }
        $nf = settings('locale.number_format', '1.234,56');
        $decimalSep = $nf === '1,234.56' ? '.' : ','; // which character shows decimals
        $thousandSep = $nf === '1,234.56' ? ',' : '.'; // which character groups thousands
        return number_format((float) $num, $decimals, $decimalSep, $thousandSep);
    }
}
