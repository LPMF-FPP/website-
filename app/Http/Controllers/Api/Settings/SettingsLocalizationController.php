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
            AppTimezone::apply();

            $appNow = Carbon::now(config('app.timezone'));
            $utcNow = Carbon::now('UTC');

            return response()->json([
                'app_timezone' => config('app.timezone'),
                'php_timezone' => date_default_timezone_get(),
                'now_app' => $appNow->toIso8601String(),
                'now_utc' => $utcNow->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Preview time failed.',
            ], 500);
        }
    }
}
