<?php

namespace App\Services\AI;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiConfigurationLock
{
    public function acquire(string $busyMessage): Lock
    {
        $storeName = config('services.ai.lock_store', 'database');
        if (! app()->environment('testing') && ! in_array(config('cache.stores.'.$storeName.'.driver'), ['database', 'redis'], true)) {
            throw new AiFailure('AI_LIMITS_UNAVAILABLE', 'Penyimpanan konfigurasi AI bersama belum siap. Hubungi administrator.');
        }

        try {
            $lock = Cache::store($storeName)->lock('ai:configuration', $this->lockTtl());
            if (! $lock->get()) {
                throw new AiFailure('AI_CONFIGURATION_BUSY', $busyMessage, 409);
            }

            return $lock;
        } catch (AiFailure $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AiFailure('AI_LIMITS_UNAVAILABLE', 'Konfigurasi AI tidak dapat dikunci dengan aman. Coba kembali.');
        }
    }

    public function release(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (\Throwable) {
            Log::warning('AI configuration lock release failed', ['code' => 'AI_LOCK_RELEASE_FAILED']);
        }
    }

    private function lockTtl(): int
    {
        $requestWindow = (int) config('services.ai.timeout', 30)
            + (int) config('services.ai.connect_timeout', 5)
            + (int) config('services.ai.dns_timeout', 3)
            + 10;

        return max(60, $requestWindow);
    }
}
