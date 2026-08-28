<?php

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateQuiescence;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdateScope;
use App\Models\User;
use App\Services\WhatsApp\GowaEvidenceImporter;
use App\Services\WhatsApp\GowaUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function gowaContractService(): GowaUpdateService
{
    return new GowaUpdateService(
        new class implements GowaReleaseCatalog
        {
            public function generation(): ?string
            {
                return 'generation-1';
            }

            public function approved(): array
            {
                return [];
            }

            public function find(string $releaseId): ?array
            {
                return null;
            }
        },
        new class implements GowaUpdateRunner
        {
            public function available(): bool
            {
                return false;
            }

            public function dispatch(array $claim): bool
            {
                return false;
            }
        },
        new class implements GowaRuntimeProbe
        {
            public function current(): array
            {
                return [];
            }

            public function isFresh(array $runtime): bool
            {
                return false;
            }
        },
    );
}

function gowaContractOperation(string $status = 'updating', string $checkpoint = 'mutation_observed'): GowaUpdateOperation
{
    $user = User::factory()->create();
    GowaUpdateScope::query()->updateOrCreate(['scope' => 'gowa'], ['current_fence' => 1]);

    return GowaUpdateOperation::query()->create([
        'id' => '00000000-0000-4000-8000-000000000000',
        'scope' => 'gowa',
        'release_id' => 'release-current',
        'requested_version' => 'v9.2.2',
        'requested_digest' => 'sha256:'.str_repeat('a', 64),
        'status' => $status,
        'idempotency_key' => 'contract:'.str()->uuid(),
        'client_action_uuid' => (string) str()->uuid(),
        'fencing_token' => 1,
        'checkpoint' => $checkpoint,
        'requested_by' => $user->id,
        'feature_snapshot' => ['last_evidence_sequence' => []],
        'heartbeat_at' => now()->subMinutes(20),
        'lease_expires_at' => now()->subMinutes(10),
    ]);
}

it('accepts the first evidence, replays it idempotently, and rejects a sequence gap', function (): void {
    $service = gowaContractService();
    $operation = gowaContractOperation();
    $base = [
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'plane' => 'root',
        'occurred_at' => '2026-08-28T00:00:00+00:00',
        'snapshot_hash' => str_repeat('a', 64),
        'container_identity' => 'container-a',
    ];

    $service->recordEvidence($base + ['sequence' => 1, 'code' => 'mutation_started']);
    $service->recordEvidence($base + ['sequence' => 1, 'code' => 'mutation_started']);
    expect($operation->fresh()->feature_snapshot['last_evidence_sequence']['root'])->toBe(1);

    expect(fn () => $service->recordEvidence($base + ['sequence' => 3, 'code' => 'mutation_observed']))
        ->toThrow(RuntimeException::class, 'evidence_sequence_gap');
});

it('keeps stale reconciliation fail-closed when no runtime outcome is available', function (): void {
    $service = gowaContractService();
    $operation = gowaContractOperation('preparing', 'mutation_prepared');

    $service->reconcileStale($operation);

    expect($operation->fresh()->status)->toBe('reconciling');
});

it('keeps stale replacement attempts reconciling when quiescence is unproven', function (): void {
    $service = gowaContractService();
    $operation = gowaContractOperation('updating', 'mutation_observed');

    $service->reconcileStale($operation);

    expect($operation->fresh()->status)->toBe('reconciling')
        ->and($operation->fresh()->failure_code)->toBeNull();
});

it('maps ordered evidence to lifecycle preparation, mutation, and verification states', function (): void {
    $service = gowaContractService();
    $operation = gowaContractOperation('queued', 'queued');
    $base = [
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'occurred_at' => '2026-08-28T00:00:00+00:00',
        'snapshot_hash' => str_repeat('a', 64),
        'container_identity' => 'container-a',
        'plane' => 'root',
    ];

    $service->recordEvidence($base + ['sequence' => 1, 'code' => 'mutation_prepared']);
    expect($operation->fresh()->status)->toBe('preparing');
    $service->recordEvidence($base + ['sequence' => 2, 'code' => 'mutation_started']);
    expect($operation->fresh()->status)->toBe('updating');
    $service->recordEvidence($base + ['sequence' => 3, 'code' => 'mutation_observed']);
    expect($operation->fresh()->status)->toBe('verifying');
});

it('commits degraded only with matching failed attestations and clears the active scope', function (): void {
    $service = gowaContractService();
    $operation = gowaContractOperation('updating', 'mutation_started');
    GowaUpdateScope::query()->whereKey('gowa')->update(['active_operation_id' => $operation->id]);
    $common = [
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'policy_version' => '1',
        'snapshot_hash' => str_repeat('d', 64),
        'container_identity' => 'unknown',
        'passed' => false,
        'observed_at' => now(),
    ];

    $service->recordAttestation($common + ['plane' => 'root']);
    expect(fn () => $service->commitVerifiedOutcome($operation, 'degraded'))
        ->toThrow(RuntimeException::class, 'attestation_mismatch');

    $service->recordRuntimeAttestation([
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'snapshot_hash' => $common['snapshot_hash'],
        'container_identity' => $common['container_identity'],
        'occurred_at' => now()->toIso8601String(),
        'attestation' => ['passed' => false, 'policy_version' => $common['policy_version']],
    ]);
    $service->commitVerifiedOutcome($operation, 'degraded');

    expect($operation->fresh()->status)->toBe('degraded')
        ->and(GowaUpdateScope::query()->whereKey('gowa')->value('active_operation_id'))->toBeNull();
});

it('does not permit terminal retry until server-side quiescence is proven', function (): void {
    $service = new GowaUpdateService(
        new class implements GowaReleaseCatalog
        {
            public function generation(): ?string
            {
                return 'generation-1';
            }

            public function approved(): array
            {
                return [['release_id' => 'release-current', 'digest' => 'sha256:'.str_repeat('a', 64)]];
            }

            public function find(string $releaseId): ?array
            {
                return $releaseId === 'release-current' ? ['release_id' => $releaseId, 'digest' => 'sha256:'.str_repeat('a', 64)] : null;
            }
        },
        new class implements GowaUpdateQuiescence, GowaUpdateRunner
        {
            public function available(): bool
            {
                return true;
            }

            public function dispatch(array $claim): bool
            {
                return true;
            }

            public function quiescence(string $operationId): array
            {
                return ['quiescent' => false, 'systemd' => true, 'lock' => false, 'request' => true, 'evidence' => true];
            }
        },
        new class implements GowaRuntimeProbe
        {
            public function current(): array
            {
                return ['health' => 'healthy', 'digest' => 'sha256:'.str_repeat('a', 64), 'container_identity' => 'container-test'];
            }

            public function isFresh(array $runtime): bool
            {
                return true;
            }
        },
    );
    $operation = gowaContractOperation('failed', 'terminal');

    expect(fn () => $service->retry($operation, $operation->requested_by))
        ->toThrow(RuntimeException::class, 'operation_not_retryable');
});

it('does not accept forged evidence through the importer contract', function (): void {
    $directory = sys_get_temp_dir().'/gowa-contract-'.bin2hex(random_bytes(4));
    mkdir($directory, 0700, true);
    $path = $directory.'/evidence.json';
    file_put_contents($path, json_encode([
        'schema_version' => 1,
        'payload' => ['contract' => 'gowa-evidence-v1'],
        'signature' => base64_encode(random_bytes(64)),
    ], JSON_THROW_ON_ERROR));

    try {
        expect(fn () => (new GowaEvidenceImporter)->decode($path))
            ->toThrow(RuntimeException::class, 'evidence_key_unavailable');
    } finally {
        unlink($path);
        rmdir($directory);
    }
});

it('lets a fresh successful evidence stream finish a stale operation without stranding it', function (): void {
    $catalog = new class implements GowaReleaseCatalog
    {
        public function generation(): ?string
        {
            return 'generation-1';
        }

        public function approved(): array
        {
            return [];
        }

        public function find(string $releaseId): ?array
        {
            return null;
        }
    };
    $runner = new class implements GowaUpdateQuiescence, GowaUpdateRunner
    {
        public function available(): bool
        {
            return true;
        }

        public function dispatch(array $claim): bool
        {
            return true;
        }

        public function quiescence(string $operationId): array
        {
            return ['quiescent' => true, 'systemd' => true, 'lock' => true, 'request' => true, 'evidence' => true];
        }
    };
    $probe = new class implements GowaRuntimeProbe
    {
        public function current(): array
        {
            return ['container_identity' => 'container-a'];
        }

        public function isFresh(array $runtime): bool
        {
            return true;
        }
    };
    $service = new GowaUpdateService($catalog, $runner, $probe);
    $operation = gowaContractOperation('updating', 'mutation_started');
    $base = [
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'occurred_at' => now()->toIso8601String(),
        'snapshot_hash' => str_repeat('a', 64),
        'container_identity' => 'container-a',
        'plane' => 'root',
    ];

    $service->recordEvidence($base + ['sequence' => 1, 'code' => 'mutation_observed']);
    $service->recordAttestation($base + [
        'plane' => 'root',
        'policy_version' => '1',
        'passed' => true,
        'observed_at' => $base['occurred_at'],
    ]);
    $service->recordRuntimeAttestation($base + [
        'attestation' => ['passed' => true],
    ]);
    $operation->update(['lease_expires_at' => now()->subSecond()]);
    $service->reconcileStale($operation);

    expect($operation->fresh()->status)->toBe('succeeded')
        ->and($operation->fresh()->lease_expires_at)->toBeNull();
});
