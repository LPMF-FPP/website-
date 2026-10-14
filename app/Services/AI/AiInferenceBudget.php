<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class AiInferenceBudget
{
    public function reserve(): void
    {
        $storeName = config('services.ai.lock_store', 'database');
        if (! app()->environment('testing') && ! in_array(config('cache.stores.'.$storeName.'.driver'), ['database', 'redis'], true)) {
            throw new AiFailure('AI_LIMITS_UNAVAILABLE', 'Penyimpanan batas AI belum siap. Hubungi administrator.');
        }

        try {
            $store = Cache::store($storeName);
            $lock = $store->lock('ai:budget:lock', 5);
            $reserved = $lock->get(function () use ($store): bool {
                $key = 'ai:budget:'.now()->format('YmdHi');
                $used = (int) $store->get($key, 0);
                if ($used >= max(0, (int) config('services.ai.global_budget', 60))) {
                    return false;
                }

                $store->put($key, $used + 1, 120);

                return true;
            });
        } catch (\Throwable) {
            throw new AiFailure('AI_LIMITS_UNAVAILABLE', 'Batas penggunaan AI tidak dapat diverifikasi. Hubungi administrator.');
        }

        if (! $reserved) {
            throw new AiFailure('AI_BUDGET_LIMIT', 'Batas penggunaan AI bersama tercapai. Coba lagi pada menit berikutnya.', 429);
        }
    }
}
