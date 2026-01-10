<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    /**
     * Comprehensive health check endpoint.
     * Verifies: database, cache, queue, storage.
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'healthy');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'commit' => env('APP_COMMIT', null),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * Liveness probe - basic app responsiveness.
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Readiness probe - app ready to serve traffic.
     */
    public function readiness(): JsonResponse
    {
        $dbHealthy = $this->checkDatabase()['status'] === 'healthy';

        return response()->json([
            'status' => $dbHealthy ? 'ready' : 'not_ready',
            'timestamp' => now()->toISOString(),
        ], $dbHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $duration,
                'message' => 'Database connection successful',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . now()->timestamp;
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($value !== 'test') {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Cache read/write mismatch',
                ];
            }

            return [
                'status' => 'healthy',
                'response_time_ms' => $duration,
                'driver' => config('cache.default'),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $size = Queue::size($connection);

            return [
                'status' => 'healthy',
                'driver' => $connection,
                'pending_jobs' => $size,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $start = microtime(true);
            $disk = config('filesystems.default');
            $testFile = 'health_check_' . now()->timestamp . '.txt';
            
            Storage::disk($disk)->put($testFile, 'health check');
            $exists = Storage::disk($disk)->exists($testFile);
            Storage::disk($disk)->delete($testFile);
            
            $duration = round((microtime(true) - $start) * 1000, 2);

            if (!$exists) {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Storage write/read failed',
                ];
            }

            return [
                'status' => 'healthy',
                'response_time_ms' => $duration,
                'driver' => $disk,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
