<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SystemController extends Controller
{
    /**
     * Admin phone numbers allowed to restart queue
     */
    protected array $adminPhones = [
        '6282264467992',
        '6285956592404',
    ];

    /**
     * Restart queue workers
     * Called from WhatsApp bot /restart command
     */
    public function restartQueue(Request $request)
    {
        $isProduction = app()->environment('production');
        $allowInProduction = (bool) Config::get('services.whatsapp.allow_restart_in_production', false);
        if ($isProduction && ! $allowInProduction) {
            Log::warning('Restart queue endpoint blocked in production', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Endpoint disabled in production',
            ], 403);
        }

        $systemToken = trim((string) $request->header('X-System-Token'));
        $expectedToken = trim((string) Config::get('services.whatsapp.restart_token', env('WHATSAPP_RESTART_TOKEN')));
        if ($systemToken === '' || $expectedToken === '' || ! hash_equals($expectedToken, $systemToken)) {
            Log::warning('Unauthorized queue restart attempt: invalid token', [
                'phone' => $request->header('X-Admin-Phone'),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $allowlistRaw = Config::get('services.whatsapp.restart_ip_allowlist', env('WHATSAPP_RESTART_IP_ALLOWLIST'));
        $allowlist = is_string($allowlistRaw)
            ? array_values(array_filter(array_map('trim', explode(',', $allowlistRaw))))
            : (is_array($allowlistRaw) ? array_values(array_filter(array_map('strval', $allowlistRaw))) : []);

        if (count($allowlist) > 0 && ! in_array((string) $request->ip(), $allowlist, true)) {
            Log::warning('Unauthorized queue restart attempt: IP not allowlisted', [
                'phone' => $request->header('X-Admin-Phone'),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $adminPhone = $request->header('X-Admin-Phone');

        // Verify admin phone
        if (! in_array($adminPhone, $this->adminPhones, true)) {
            Log::warning('Unauthorized queue restart attempt', [
                'phone' => $adminPhone,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            Log::info('Queue restart requested by admin', ['phone' => $adminPhone]);

            // Restart queue workers
            Artisan::call('queue:restart');

            // Optionally clear cache
            // Artisan::call('cache:clear');

            Log::info('Queue restart successful', ['phone' => $adminPhone]);

            return response()->json([
                'success' => true,
                'message' => 'Queue restart signal sent successfully',
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            Log::error('Queue restart failed', [
                'phone' => $adminPhone,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restart queue: '.$e->getMessage(),
            ], 500);
        }
    }
}
