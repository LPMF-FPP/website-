<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaReleasePreparationRunner;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Jobs\PrepareGowaReleaseJob;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdatePreparation;
use App\Support\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class GowaUpdatePreparationService
{
    public function __construct(
        private readonly GowaUpstreamReleaseChecker $releaseChecker,
        private readonly GowaReleasePreparationRunner $runner,
        private readonly GowaRuntimeProbe $probe,
        private readonly GowaReleaseCatalog $catalog,
    ) {}

    public function startLatest(string $actionUuid, int $userId): GowaUpdatePreparation
    {
        $idempotencyKey = $userId.':'.$actionUuid;
        $existing = GowaUpdatePreparation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (! $this->runner->available()) {
            throw new RuntimeException('preparation_runner_unavailable');
        }

        if (GowaUpdateOperation::query()->where('scope', GowaUpdateOperation::SCOPE)->whereIn('status', GowaUpdateOperation::ACTIVE_STATUSES)->exists()) {
            throw new RuntimeException('update_already_active');
        }

        try {
            $check = $this->releaseChecker->check(true);
        } catch (\Throwable $exception) {
            throw new RuntimeException('upstream_release_unavailable', 0, $exception);
        }
        if (($check['update_available'] ?? false) !== true) {
            throw new RuntimeException(($check['blocked_reason'] ?? null) === 'runtime_stale' ? 'runtime_evidence_stale' : 'no_update_available');
        }

        $runtime = $this->probe->current();
        $health = $runtime['health'] ?? null;
        $runtimeHealthy = $health === true || $health === 'healthy' || $health === 200 || $health === '200';
        if (! $this->probe->isFresh($runtime)
            || ! $runtimeHealthy
            || ! is_string($runtime['digest'] ?? null)
            || ! preg_match('/^sha256:[0-9a-f]{64}$/', $runtime['digest'])
            || ! is_string($runtime['container_identity'] ?? null)
            || $runtime['container_identity'] === '') {
            throw new RuntimeException('runtime_evidence_unavailable');
        }

        $readyPreparation = GowaUpdatePreparation::query()
            ->where('requested_by', $userId)
            ->where('status', 'ready')
            ->where('requested_version', $check['latest_version'])
            ->where('runtime_digest', $runtime['digest'])
            ->where('container_identity', $runtime['container_identity'])
            ->where('expires_at', '>', now())
            ->latest('prepared_at')
            ->first();
        if ($readyPreparation !== null
            && ($check['approved_release_id'] ?? null) === $readyPreparation->release_id
            && ($check['approved_digest'] ?? null) === $readyPreparation->digest
            && $this->catalog->generation() === $readyPreparation->catalog_generation) {
            return $readyPreparation;
        }

        try {
            [$preparation, $dispatch] = DB::transaction(function () use ($actionUuid, $idempotencyKey, $runtime, $check, $userId): array {
                \App\Models\GowaUpdateScope::query()->whereKey(GowaUpdateOperation::SCOPE)->lockForUpdate()->firstOrFail();
                if (GowaUpdateOperation::query()->where('scope', GowaUpdateOperation::SCOPE)->whereIn('status', GowaUpdateOperation::ACTIVE_STATUSES)->exists()) {
                    throw new RuntimeException('update_already_active');
                }
                $activePreparation = GowaUpdatePreparation::query()
                    ->whereIn('status', ['queued', 'preparing'])
                    ->lockForUpdate()
                    ->first();
                if ($activePreparation !== null) {
                    if ((int) $activePreparation->requested_by === $userId
                        && $activePreparation->requested_version === $check['latest_version']) {
                        return [$activePreparation, false];
                    }
                    if ($activePreparation->created_at?->lessThan(now()->subMinutes(max(1, (int) config('gowa-updater.preparation_ttl_minutes', 30)))) !== true) {
                        throw new RuntimeException('preparation_already_active');
                    }
                    $activePreparation->update(['status' => 'failed', 'failure_code' => 'preparation_expired']);
                }

                $preparation = GowaUpdatePreparation::query()->create([
                    'id' => (string) Str::uuid(),
                    'requested_by' => $userId,
                    'action_uuid' => $actionUuid,
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'queued',
                    'requested_version' => $check['latest_version'],
                    'runtime_digest' => $runtime['digest'],
                    'container_identity' => $runtime['container_identity'],
                    'requested_at' => now(),
                ]);

                return [$preparation, true];
            });
        } catch (QueryException $exception) {
            $replayed = GowaUpdatePreparation::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($replayed !== null) {
                return $replayed;
            }
            $active = GowaUpdatePreparation::query()
                ->whereIn('status', ['queued', 'preparing'])
                ->latest('created_at')
                ->first();
            if ($active !== null
                && $active->requested_by === $userId
                && $active->requested_version === $check['latest_version']) {
                return $active;
            }
            if (str_contains(strtolower($exception->getMessage()), 'gowa_update_preparations_one_active')) {
                throw new RuntimeException('preparation_already_active', 0, $exception);
            }
            throw $exception;
        }

        if ($dispatch) {
            try {
                PrepareGowaReleaseJob::dispatch($preparation->id);
            } catch (\Throwable $exception) {
                $preparation->update(['status' => 'failed', 'failure_code' => 'preparation_queue_unavailable']);
                throw new RuntimeException('preparation_queue_unavailable', 0, $exception);
            }
            ActivityLogger::log(
                'GOWA_UPDATE_PREPARATION_REQUESTED',
                $userId,
                ['type' => 'gowa_update_preparation'],
                null,
                null,
                ['preparation_id' => $preparation->id, 'requested_version' => $preparation->requested_version],
            );
        }

        return $preparation;
    }

    public function forUser(string $id, int $userId): GowaUpdatePreparation
    {
        return GowaUpdatePreparation::query()
            ->whereKey($id)
            ->where('requested_by', $userId)
            ->firstOrFail();
    }

    public function assertInstallable(string $id, int $userId, bool $retry = false): GowaUpdatePreparation
    {
        if (! $this->runner->available()) {
            throw new RuntimeException('preparation_not_ready');
        }

        $preparation = $this->forUser($id, $userId);
        $latestPreparationId = GowaUpdatePreparation::query()
            ->where('requested_by', $userId)
            ->latest('created_at')
            ->value('id');
        if ($latestPreparationId !== $preparation->id) {
            throw new RuntimeException('preparation_not_ready');
        }
        $runtime = $this->probe->current();
        $health = $runtime['health'] ?? null;
        $runtimeHealthy = $health === true || $health === 'healthy' || $health === 200 || $health === '200';
        $preparationReusable = $retry && $preparation->status === 'consumed' && $preparation->consumed_at !== null;
        if ((! $preparationReusable && $preparation->status !== 'ready')
            || $preparation->expires_at?->isFuture() !== true
            || (! $preparationReusable && $preparation->consumed_at !== null)
            || ! $this->probe->isFresh($runtime)
            || ! $runtimeHealthy
            || ($runtime['digest'] ?? null) !== $preparation->runtime_digest
            || ($runtime['container_identity'] ?? null) !== $preparation->container_identity
            || GowaUpdateOperation::query()->where('scope', GowaUpdateOperation::SCOPE)->whereIn('status', GowaUpdateOperation::ACTIVE_STATUSES)->exists()
            || GowaUpdatePreparation::query()->whereIn('status', ['queued', 'preparing'])->where('id', '!=', $preparation->id)->exists()) {
            throw new RuntimeException('preparation_not_ready');
        }

        try {
            $check = $this->releaseChecker->check(true);
        } catch (\Throwable $exception) {
            throw new RuntimeException('upstream_release_unavailable', 0, $exception);
        }
        $release = $this->catalog->find((string) $preparation->release_id);
        if (($check['can_update'] ?? false) !== true
            || ($check['approved_release_id'] ?? null) !== $preparation->release_id
            || ($check['approved_digest'] ?? null) !== $preparation->digest
            || $release === null
            || $release['digest'] !== $preparation->digest
            || $this->catalog->generation() !== $preparation->catalog_generation) {
            throw new RuntimeException('release_not_latest');
        }

        return $preparation;
    }
}
