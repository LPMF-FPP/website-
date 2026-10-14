<?php

namespace App\Services\AI;

use App\Models\SystemSetting;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class AiTelemetryPrivacy
{
    public static function register(): void
    {
        Telescope::hideRequestParameters(['ai_api_key', 'current_text', 'prompt']);
        Telescope::hideRequestHeaders(['authorization']);
        Telescope::filter(static fn (IncomingEntry $entry): bool => self::allowsRecord($entry));
    }

    public static function sensitiveQuery(string $sql, array $bindings = []): bool
    {
        if (preg_match('/\bsettings\b/i', $sql) || str_contains($sql, 'notifications.whatsapp.ai.') || str_contains($sql, 'sys_settings_all')) {
            return true;
        }
        foreach ($bindings as $binding) {
            if (is_string($binding) && (str_contains($binding, 'notifications.whatsapp.ai.') || str_contains($binding, 'sys_settings_all'))) {
                return true;
            }
        }

        return false;
    }

    public static function allowsRecord(IncomingEntry $entry): bool
    {
        $content = $entry->content;
        $uri = (string) ($content['uri'] ?? '');
        $path = rtrim((string) parse_url($uri, PHP_URL_PATH), '/');
        if (preg_match('#/whatsapp/(ai/compose|settings/(ai|test-ai))$#', $path)) {
            return false;
        }
        if (str_ends_with($path, '/chat/completions') && in_array(parse_url($uri, PHP_URL_HOST), config('services.ai.approved_hosts', []), true)) {
            return false;
        }
        // Updated setting model events identify the row by ID, not by key, so all value-bearing setting diagnostics are excluded.
        if (str_starts_with((string) ($content['model'] ?? ''), SystemSetting::class)
            || str_contains((string) ($content['name'] ?? ''), SystemSetting::class)
            || str_starts_with((string) ($content['name'] ?? ''), 'Illuminate\\Http\\Client\\Events\\')
            || str_ends_with((string) ($content['key'] ?? ''), 'sys_settings_all')
            || (isset($content['sql']) && self::sensitiveQuery($content['sql'], $content['bindings'] ?? []))) {
            return false;
        }

        return true;
    }
}
