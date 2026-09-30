<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use Illuminate\Console\Command;

final class GowaUpdaterPreflight extends Command
{
    protected $signature = 'gowa-updater:preflight';

    protected $description = 'Verify the read-only prerequisites for GOWA release preparation.';

    public function handle(
        GowaRuntimeProbe $probe,
        GowaReleaseCatalog $catalog,
        GowaUpdateRunner $runner,
    ): int {
        try {
            $runtime = $probe->current();
            $health = $runtime['health'] ?? null;
            $healthy = $health === true || $health === 'healthy' || $health === 200 || $health === '200';
            $runtimeReady = $probe->isFresh($runtime)
                && $healthy
                && is_string($runtime['digest'] ?? null)
                && preg_match('/^sha256:[0-9a-f]{64}$/', $runtime['digest']) === 1
                && is_string($runtime['container_identity'] ?? null)
                && $runtime['container_identity'] !== '';
            $catalogReady = $catalog->generation() !== null;
            $runnerReady = $runner->available();
        } catch (\Throwable) {
            $this->error('preflight_failed');

            return self::FAILURE;
        }

        if (! $runtimeReady || ! $catalogReady || ! $runnerReady) {
            $this->error('preflight_not_ready');

            return self::FAILURE;
        }

        $this->info('preflight_ready');

        return self::SUCCESS;
    }
}
