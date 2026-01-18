<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Support\AppTimezone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsLocalizationController extends Controller
{
    public function timePreview(Request $request): JsonResponse
    {
        Gate::authorize('manage-settings');

        try {
            // Force clear cache to ensure fresh settings are applied for preview
            // This fixes the issue where preview shows stale timezone (e.g. UTC) after update
            settings_flush_cache();
            \Illuminate\Support\Facades\Cache::forget(AppTimezone::CACHE_KEY);

            AppTimezone::apply();

            $appNow = Carbon::now(config('app.timezone'));
            $utcNow = Carbon::now('UTC');

            return response()->json([
                'app_timezone' => config('app.timezone'),
                'php_timezone' => date_default_timezone_get(),
                'now_app' => $appNow->toIso8601String(),
                'now_utc' => $utcNow->toIso8601String(),
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Preview time failed.',
            ], 500);
        }
    }
}
