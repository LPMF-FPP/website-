<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Jobs\DispatchGowaUpdateJob;
use App\Models\GowaUpdateAttestation;
use App\Models\GowaUpdateEvent;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdateScope;
use App\Support\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class GowaUpdateService
{
    public function __construct(
        private readonly GowaReleaseCatalog $catalog,
        private readonly GowaUpdateRunner $runner,
        private readonly GowaRuntimeProbe $probe,
    ) {}

    public function status(): array
    {
        $runtime = $this->probe->current();
        $runtimeFresh = $this->probe->isFresh($runtime);
        try {
            $catalogGeneration = $this->catalog->generation();
            $releases = $this->catalog->approved();
        } catch (\Throwable) {
            $catalogGeneration = null;
            $releases = [];
        }
        try {
            $runnerAvailable = $this->runner->available();
        } catch (\Throwable) {
            $runnerAvailable = false;
        }

        try {
            $latestOperation = GowaUpdateOperation::query()->latest('created_at')->first()?->safeProjection();
        } catch (\Throwable) {
            $latestOperation = null;
        }

        return [
            'available' => $runnerAvailable && $runtimeFresh && $catalogGeneration !== null,
            'reason' => $runnerAvailable ? ($runtimeFresh ? ($catalogGeneration !== null ? null : 'catalog_unavailable') : 'runtime_evidence_stale') : 'privileged_runner_unavailable',
            'catalog_generation' => $catalogGeneration,
            'runtime' => $this->safeRuntime($runtime),
            'releases' => array_map(static fn (array $release): array => [
                'release_id' => $release['release_id'],
                'version' => $release['version'] ?? null,
                'digest' => $release['digest'],
            ], $releases),
            'latest_operation' => $latestOperation,
        ];
    }

    public function create(string $releaseId, string $actionUuid, int $userId, ?string $retryOfId = null): GowaUpdateOperation
    {
        $idempotencyKey = $userId.':'.$actionUuid;

        $existing = GowaUpdateOperation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            if ($existing->release_id !== $releaseId) {
                throw new RuntimeException('idempotency_payload_mismatch');
            }

            return $existing;
        }

        if (! $this->runner->available()) {
            throw new RuntimeException('privileged_runner_unavailable');
        }

        if (! $this->runtimeReadyForUpdate()) {
            throw new RuntimeException('runtime_evidence_unavailable');
        }

        $release = $this->catalog->find($releaseId);
        if ($release === null) {
            throw new RuntimeException('release_not_allowed');
        }

        try {
            $operation = DB::transaction(function () use ($actionUuid, $idempotencyKey, $release, $retryOfId, $userId): GowaUpdateOperation {
                $existing = GowaUpdateOperation::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($existing->release_id !== $release['release_id']) {
                        throw new RuntimeException('idempotency_payload_mismatch');
                    }

                    return $existing;
                }

                if (! $this->runner->available()) {
                    throw new RuntimeException('privileged_runner_unavailable');
                }

                if (! $this->runtimeReadyForUpdate()) {
                    throw new RuntimeException('runtime_evidence_unavailable');
                }

                $scope = GowaUpdateScope::query()->whereKey(GowaUpdateOperation::SCOPE)->lockForUpdate()->firstOrFail();
                $active = GowaUpdateOperation::query()->where('scope', GowaUpdateOperation::SCOPE)->whereIn('status', GowaUpdateOperation::ACTIVE_STATUSES)->exists();
                if ($active) {
                    throw new RuntimeException('update_already_active');
                }

                $scope->increment('current_fence');
                $scope->refresh();
                $operation = GowaUpdateOperation::query()->create([
                    'id' => (string) Str::uuid(),
                    'scope' => GowaUpdateOperation::SCOPE,
                    'release_id' => $release['release_id'],
                    'requested_version' => $release['version'] ?? 'unknown',
                    'requested_digest' => $release['digest'],
                    'status' => 'queued',
                    'idempotency_key' => $idempotencyKey,
                    'client_action_uuid' => $actionUuid,
                    'fencing_token' => $scope->current_fence,
                    'checkpoint' => 'queued',
                    'heartbeat_at' => now(),
                    'lease_expires_at' => now()->addMinutes(10),
                    'requested_by' => $userId,
                    'retry_of_id' => $retryOfId,
                    'feature_snapshot' => [
                        'catalog_generation' => $this->catalog->generation(),
                        'revocation_generation' => (string) ($release['revocation_generation'] ?? 'initial'),
                    ],
                ]);
                $scope->update(['active_operation_id' => $operation->id]);
                $this->event($operation, null, 'queued', 'request_accepted');

                return $operation;
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'idempotency_key')) {
                $replayed = GowaUpdateOperation::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($replayed !== null) {
                    if ($replayed->release_id !== $release['release_id']) {
                        throw new RuntimeException('idempotency_payload_mismatch', 0, $exception);
                    }

                    return $replayed;
                }
            }
            if (str_contains(strtolower($exception->getMessage()), 'gowa_update_operations_one_active')) {
                throw new RuntimeException('update_already_active', 0, $exception);
            }
            throw $exception;
        }

        ActivityLogger::log('GOWA_UPDATE_REQUESTED', $userId, ['type' => 'gowa_update_operation'], null, null, ['operation_id' => $operation->id, 'release_id' => $operation->release_id]);

        DispatchGowaUpdateJob::dispatch($operation->id);

        return $operation->fresh();
    }

    public function markReconciling(GowaUpdateOperation $operation): void
    {
        DB::transaction(function () use ($operation): void {
            $locked = GowaUpdateOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($locked->isTerminal() || $locked->status === 'reconciling') {
                return;
            }
            $from = $locked->status;
            $locked->update([
                'status' => 'reconciling',
                'checkpoint' => 'lease_expired',
                'heartbeat_at' => now(),
                'lease_expires_at' => now()->addMinutes((int) config('gowa-updater.lease_minutes', 10)),
            ]);
            $this->event($locked, $from, 'reconciling', 'execution_lease_expired');
        });
    }

    public function reconcileStale(GowaUpdateOperation $operation): void
    {
        DB::transaction(function () use ($operation): void {
            $locked = GowaUpdateOperation::query()->lockForUpdate()->find($operation->id);
            if ($locked === null || $locked->isTerminal() || $locked->lease_expires_at?->isFuture()) {
                return;
            }

            $from = $locked->status;
            $locked->update([
                'status' => 'reconciling',
                'checkpoint' => 'lease_expired',
                'failure_code' => null,
                'failure_message_key' => null,
                'heartbeat_at' => now(),
                'lease_expires_at' => now()->addMinutes((int) config('gowa-updater.lease_minutes', 10)),
            ]);
            $this->event($locked, $from, 'reconciling', 'execution_lease_expired');

            $locked->update([
                'status' => 'degraded',
                'checkpoint' => 'terminal',
                'failure_code' => 'reconciliation_failed',
                'failure_message_key' => 'gowa_update.reconciliation_failed',
            ]);
            GowaUpdateScope::query()->whereKey($locked->scope)->update(['active_operation_id' => null]);
            $this->event($locked, 'reconciling', 'degraded', 'reconciliation_failed');
        });
    }

    public function retry(GowaUpdateOperation $previous, int $userId): GowaUpdateOperation
    {
        if ($previous->scope !== GowaUpdateOperation::SCOPE
            || ! $previous->isTerminal()
            || GowaUpdateOperation::query()->where('scope', GowaUpdateOperation::SCOPE)->whereIn('status', GowaUpdateOperation::ACTIVE_STATUSES)->exists()
            || ! $this->runtimeReadyForUpdate()) {
            throw new RuntimeException('operation_not_retryable');
        }

        return $this->create($previous->release_id, (string) Str::uuid(), $userId, $previous->id);
    }

    public function fail(GowaUpdateOperation $operation, string $code): void
    {
        DB::transaction(function () use ($operation, $code): void {
            $locked = GowaUpdateOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($locked->isTerminal()) {
                return;
            }
            $from = $locked->status;
            $locked->update(['status' => 'failed', 'failure_code' => $code, 'failure_message_key' => 'gowa_update.'.$code, 'checkpoint' => 'terminal']);
            GowaUpdateScope::query()->whereKey($locked->scope)->update(['active_operation_id' => null]);
            $this->event($locked, $from, 'failed', $code);
        });
    }

    /** @param array<string, mixed> $evidence */
    public function recordEvidence(array $evidence): void
    {
        if (! is_string($evidence['operation_id'] ?? null)
            || ! preg_match('/^[0-9a-f-]{36}$/i', $evidence['operation_id'])
            || ! is_int($evidence['fencing_token'] ?? null)
            || ! is_int($evidence['sequence'] ?? null)
            || ! in_array($evidence['plane'] ?? null, ['root', 'runtime'], true)
            || ! is_string($evidence['code'] ?? null)
            || ! preg_match('/^[a-z0-9_]{1,64}$/', $evidence['code'])) {
            throw new RuntimeException('evidence_rejected');
        }

        DB::transaction(function () use ($evidence): void {
            $operation = GowaUpdateOperation::query()->lockForUpdate()->find($evidence['operation_id'] ?? null);
            if ($operation === null || $operation->isTerminal() || $operation->fencing_token !== $evidence['fencing_token']) {
                throw new RuntimeException('evidence_rejected');
            }

            $sequence = (int) $evidence['sequence'];
            $plane = (string) $evidence['plane'];
            $snapshot = $operation->feature_snapshot ?? [];
            $lastSequence = (int) (($snapshot['last_evidence_sequence'] ?? [])[$plane] ?? 0);
            if ($sequence <= $lastSequence) {
                return;
            }
            if ($sequence !== $lastSequence + 1) {
                throw new RuntimeException('evidence_sequence_gap');
            }

            $operation->update(['feature_snapshot' => array_merge($operation->feature_snapshot ?? [], [
                'last_evidence_sequence' => array_merge($snapshot['last_evidence_sequence'] ?? [], [$plane => $sequence]),
                'last_evidence_code' => $evidence['code'],
            ])]);
            $this->event($operation, $operation->status, $operation->status, $evidence['code']);
        });
    }

    /** @param array<string, mixed> $attestation */
    public function recordAttestation(array $attestation): GowaUpdateAttestation
    {
        if (! in_array($attestation['plane'] ?? null, ['root', 'runtime'], true)
            || ! is_string($attestation['policy_version'] ?? null)
            || $attestation['policy_version'] === ''
            || ! is_string($attestation['snapshot_hash'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/', $attestation['snapshot_hash'])
            || ! is_string($attestation['container_identity'] ?? null)
            || $attestation['container_identity'] === '') {
            throw new RuntimeException('attestation_rejected');
        }
        try {
            new \DateTimeImmutable((string) ($attestation['observed_at'] ?? ''));
        } catch (\Throwable) {
            throw new RuntimeException('attestation_rejected');
        }

        return DB::transaction(function () use ($attestation): GowaUpdateAttestation {
            $operation = GowaUpdateOperation::query()->lockForUpdate()->find($attestation['operation_id'] ?? null);
            if ($operation === null || $operation->isTerminal() || $operation->fencing_token !== (int) ($attestation['fencing_token'] ?? 0)) {
                throw new RuntimeException('attestation_rejected');
            }

            $existing = GowaUpdateAttestation::query()->where([
                'operation_id' => $operation->id,
                'fencing_token' => $operation->fencing_token,
                'plane' => $attestation['plane'],
            ])->first();
            if ($existing !== null) {
                if ($existing->policy_version !== $attestation['policy_version']
                    || $existing->snapshot_hash !== $attestation['snapshot_hash']
                    || $existing->container_identity !== $attestation['container_identity']
                    || $existing->passed !== (bool) $attestation['passed']) {
                    throw new RuntimeException('attestation_conflict');
                }

                return $existing;
            }

            return GowaUpdateAttestation::query()->create([
                'operation_id' => $operation->id,
                'fencing_token' => $operation->fencing_token,
                'plane' => $attestation['plane'],
                'policy_version' => $attestation['policy_version'],
                'snapshot_hash' => $attestation['snapshot_hash'],
                'container_identity' => $attestation['container_identity'],
                'passed' => (bool) $attestation['passed'],
                'observed_at' => $attestation['observed_at'],
            ]);
        });
    }

    public function commitVerifiedOutcome(GowaUpdateOperation $operation, string $terminalStatus): void
    {
        if (! in_array($terminalStatus, ['succeeded', 'rolled_back'], true)) {
            throw new RuntimeException('invalid_terminal_outcome');
        }

        DB::transaction(function () use ($operation, $terminalStatus): void {
            $locked = GowaUpdateOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($locked->isTerminal()) {
                return;
            }

            $attestations = $locked->attestations()->where('fencing_token', $locked->fencing_token)->get();
            $root = $attestations->firstWhere('plane', 'root');
            $runtime = $attestations->firstWhere('plane', 'runtime');
            $valid = $root !== null && $runtime !== null
                && $root->passed && $runtime->passed
                && $root->policy_version === $runtime->policy_version
                && $root->snapshot_hash === $runtime->snapshot_hash
                && $root->container_identity === $runtime->container_identity
                && $root->observed_at?->greaterThanOrEqualTo(now()->subMinutes(5))
                && $runtime->observed_at?->greaterThanOrEqualTo(now()->subMinutes(5));
            if (! $valid) {
                throw new RuntimeException('attestation_mismatch');
            }

            $from = $locked->status;
            $locked->update(['status' => $terminalStatus, 'checkpoint' => 'terminal']);
            GowaUpdateScope::query()->whereKey($locked->scope)->update(['active_operation_id' => null]);
            $this->event($locked, $from, $terminalStatus, 'verified_terminal_outcome');
        });
    }

    private function event(GowaUpdateOperation $operation, ?string $from, string $to, string $code): void
    {
        GowaUpdateEvent::query()->create([
            'operation_id' => $operation->id,
            'runner_event_id' => (string) Str::uuid(),
            'fencing_token' => $operation->fencing_token,
            'from_state' => $from,
            'to_state' => $to,
            'code' => $code,
            'safe_meta' => ['release_id' => $operation->release_id],
            'occurred_at' => now(),
        ]);
    }

    private function safeRuntime(array $runtime): array
    {
        return array_intersect_key($runtime, array_flip(['version', 'digest', 'container_identity', 'observed_at', 'health', 'fresh']));
    }

    private function runtimeReadyForUpdate(): bool
    {
        try {
            $runtime = $this->probe->current();
            if (! $this->probe->isFresh($runtime)) {
                return false;
            }

            $health = $runtime['health'] ?? null;
            $healthy = $health === true || $health === 'healthy' || $health === 200 || $health === '200';

            return $healthy
                && is_string($runtime['digest'] ?? null)
                && preg_match('/^sha256:[0-9a-f]{64}$/', $runtime['digest']) === 1
                && is_string($runtime['container_identity'] ?? null)
                && $runtime['container_identity'] !== '';
        } catch (\Throwable) {
            return false;
        }
    }
}
