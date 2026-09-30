<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WhatsApp\GowaReleasePreparationRunner;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Models\GowaUpdatePreparation;
use App\Services\WhatsApp\GowaUpstreamReleaseChecker;
use App\Support\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PrepareGowaReleaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $preparationId)
    {
        $this->onConnection('gowa-maintenance')->onQueue('gowa-maintenance');
    }

    public function handle(
        GowaReleasePreparationRunner $runner,
        GowaRuntimeProbe $probe,
        GowaUpstreamReleaseChecker $releaseChecker,
    ): void {
        $preparation = DB::transaction(function (): ?GowaUpdatePreparation {
            $preparation = GowaUpdatePreparation::query()->lockForUpdate()->find($this->preparationId);
            if ($preparation === null || $preparation->status !== 'queued') {
                return null;
            }
            $preparation->update(['status' => 'preparing']);

            return $preparation;
        });
        if ($preparation === null) {
            return;
        }

        try {
            $prepared = $runner->prepareLatest();
            $check = $releaseChecker->check(true);
            $runtime = $probe->current();
            $health = $runtime['health'] ?? null;
            $runtimeHealthy = $health === true || $health === 'healthy' || $health === 200 || $health === '200';

            if (($check['update_available'] ?? false) !== true
                || ($check['latest_version'] ?? null) !== $preparation->requested_version
                || ($check['catalog_version_match'] ?? false) !== true
                || ($check['approved_release_id'] ?? null) !== $prepared['release_id']
                || ($check['approved_digest'] ?? null) !== $prepared['digest']
                || $prepared['version'] !== $preparation->requested_version
                || ! $probe->isFresh($runtime)
                || ! $runtimeHealthy
                || ($runtime['digest'] ?? null) !== $preparation->runtime_digest
                || ($runtime['container_identity'] ?? null) !== $preparation->container_identity) {
                throw new RuntimeException('preparation_evidence_mismatch');
            }

            $preparation->update([
                'status' => 'ready',
                'release_id' => $prepared['release_id'],
                'digest' => $prepared['digest'],
                'catalog_generation' => $prepared['catalog_generation'],
                'prepared_at' => now(),
                'expires_at' => now()->addMinutes(max(1, (int) config('gowa-updater.preparation_ttl_minutes', 30))),
            ]);
            ActivityLogger::log(
                'GOWA_UPDATE_PREPARATION_READY',
                $preparation->requested_by,
                ['type' => 'gowa_update_preparation'],
                null,
                null,
                ['preparation_id' => $preparation->id, 'release_id' => $prepared['release_id'], 'version' => $prepared['version']],
            );
        } catch (\Throwable $exception) {
            $failureCode = in_array($exception->getMessage(), [
                'preparation_runner_unavailable', 'preparation_failed', 'preparation_result_invalid',
                'preparation_evidence_mismatch', 'runtime_evidence_unavailable', 'runtime_evidence_stale',
                'preparation_expired',
            ], true) ? $exception->getMessage() : 'preparation_failed';
            $preparation->update(['status' => 'failed', 'failure_code' => $failureCode]);
            ActivityLogger::log(
                'GOWA_UPDATE_PREPARATION_FAILED',
                $preparation->requested_by,
                ['type' => 'gowa_update_preparation'],
                null,
                null,
                ['preparation_id' => $preparation->id, 'failure_code' => $failureCode],
            );
        }
    }
}
