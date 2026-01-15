<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
        $adminPhone = $request->header('X-Admin-Phone');
        
        // Verify admin phone
        if (!in_array($adminPhone, $this->adminPhones)) {
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
                'message' => 'Failed to restart queue: ' . $e->getMessage(),
            ], 500);
        }
    }
}
