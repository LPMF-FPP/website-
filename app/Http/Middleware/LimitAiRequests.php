<?php

namespace App\Http\Middleware;

use App\Services\AI\AiFailure;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LimitAiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->email_verified_at) {
            return (new AiFailure('AI_VERIFICATION_REQUIRED', 'Verifikasi email Anda sebelum memakai AI.', 403))->response();
        }
        $lock = null;
        try {
            $storeName = config('services.ai.lock_store', 'database');
            $limiterStore = config('cache.default', 'database');
            $sharedDrivers = ['database', 'redis'];
            if (! app()->environment('testing') && (
                ! in_array(config('cache.stores.'.$storeName.'.driver'), $sharedDrivers, true)
                || ! in_array(config('cache.stores.'.$limiterStore.'.driver'), $sharedDrivers, true)
            )) {
                return (new AiFailure('AI_LIMITS_UNAVAILABLE', 'Penyimpanan batas AI belum siap. Hubungi administrator.'))->response();
            }
            $store = Cache::store($storeName);
            $lock = $store->lock('ai:user:'.$request->user()->getAuthIdentifier(), (int) config('services.ai.timeout', 30) + 15);
            if (! $lock->get()) {
                return (new AiFailure('AI_BUSY', 'Permintaan AI Anda masih berjalan. Tunggu hingga selesai.', 429))->response();
            }
        } catch (\Throwable) {
            return (new AiFailure('AI_LIMITS_UNAVAILABLE', 'Batas penggunaan AI tidak dapat diverifikasi. Hubungi administrator.'))->response();
        }

        try {
            return $next($request);
        } finally {
            try {
                $lock?->release();
            } catch (\Throwable) {
                \Illuminate\Support\Facades\Log::warning('AI lock release failed', ['code' => 'AI_LOCK_RELEASE_FAILED']);
            }
        }
    }
}
