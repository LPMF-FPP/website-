<?php

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateQuiescence;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdateScope;
use App\Models\User;
use App\Services\WhatsApp\GowaEvidenceImporter;
use App\Services\WhatsApp\GowaUpdateReconciler;
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

    $operation = GowaUpdateOperation::query()->create([
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

    GowaUpdateScope::query()->whereKey('gowa')->update(['active_operation_id' => $operation->id]);

    return $operation;
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

it('preserves the instant of UTC and WIB attestations when PostgreSQL uses UTC', function (): void {
    $previousTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Jakarta');
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-07 12:00:00', 'Asia/Jakarta'));

    try {
        \Illuminate\Support\Facades\DB::statement("SET LOCAL TIME ZONE 'UTC'");
        $operation = gowaContractOperation('verifying');
        $service = gowaContractService();
        $base = [
            'operation_id' => $operation->id,
            'fencing_token' => 1,
            'policy_version' => '1',
            'snapshot_hash' => str_repeat('a', 64),
            'container_identity' => 'container-a',
            'passed' => true,
        ];
        $root = $service->recordAttestation($base + ['plane' => 'root', 'observed_at' => '2026-11-07T05:00:00Z']);
        $runtime = $service->recordAttestation($base + ['plane' => 'runtime', 'observed_at' => '2026-11-07T12:00:00+07:00']);

        expect($root->fresh()->observed_at->timestamp)->toBe(now()->timestamp)
            ->and($runtime->fresh()->observed_at->timestamp)->toBe(now()->timestamp);
        $service->commitVerifiedOutcome($operation, 'succeeded');
        expect($operation->fresh()->status)->toBe('succeeded');
    } finally {
        \Carbon\Carbon::setTestNow();
        date_default_timezone_set($previousTimezone);
    }
});

it('does not reconcile a live lease early when application time is WIB', function (): void {
    $previousTimezone = date_default_timezone_get();
    date_default_timezone_set('Asia/Jakarta');
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-07 12:00:00', 'Asia/Jakarta'));

    try {
        \Illuminate\Support\Facades\DB::statement("SET LOCAL TIME ZONE 'UTC'");
        config(['gowa-updater.request_root' => '', 'gowa-updater.evidence_root' => '']);
        $operation = gowaContractOperation('updating');
        $operation->update(['lease_expires_at' => now()->addMinutes(10)]);
        $reconciler = new GowaUpdateReconciler(gowaContractService(), new GowaEvidenceImporter);

        expect($reconciler->reconcile())->toBe(0)
            ->and($operation->fresh()->status)->toBe('updating')
            ->and($operation->fresh()->lease_expires_at->timestamp)->toBe(now()->addMinutes(10)->timestamp);
    } finally {
        \Carbon\Carbon::setTestNow();
        date_default_timezone_set($previousTimezone);
    }
});

it('does not verify a successful update with future-dated attestation', function (): void {
    $operation = gowaContractOperation('verifying');
    $service = gowaContractService();
    $base = [
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'policy_version' => '1',
        'snapshot_hash' => str_repeat('a', 64),
        'container_identity' => 'container-a',
        'passed' => true,
    ];
    $service->recordAttestation($base + ['plane' => 'root', 'observed_at' => now()->addMinutes(10)->toIso8601String()]);
    $service->recordAttestation($base + ['plane' => 'runtime', 'observed_at' => now()->toIso8601String()]);

    expect(fn () => $service->commitVerifiedOutcome($operation, 'succeeded'))
        ->toThrow(RuntimeException::class, 'attestation_mismatch')
        ->and($operation->fresh()->status)->toBe('verifying');
});

it('preserves old attestations and accepts renewed matching evidence for reconciliation', function (): void {
    $operation = gowaContractOperation('reconciling');
    $service = gowaContractService();
    $base = [
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'policy_version' => '1',
        'snapshot_hash' => str_repeat('a', 64),
        'container_identity' => 'container-a',
        'passed' => true,
    ];
    $renewedAt = now()->toIso8601String();
    foreach (['root', 'runtime'] as $plane) {
        $service->recordAttestation($base + ['plane' => $plane, 'observed_at' => now()->subMinutes(20)->toIso8601String()]);
        $service->recordAttestation($base + ['plane' => $plane, 'observed_at' => $renewedAt]);
        $service->recordAttestation($base + ['plane' => $plane, 'observed_at' => $renewedAt]);
    }

    $service->commitVerifiedOutcome($operation, 'succeeded');

    expect($operation->fresh()->status)->toBe('succeeded')
        ->and($operation->attestations()->count())->toBe(2)
        ->and(\Illuminate\Support\Facades\DB::table('gowa_update_attestation_renewals')->count())->toBe(2);
});

it('does not refresh an attestation with conflicting or future evidence', function (): void {
    $operation = gowaContractOperation('reconciling');
    $service = gowaContractService();
    $base = [
        'operation_id' => $operation->id, 'fencing_token' => 1, 'policy_version' => '1',
        'snapshot_hash' => str_repeat('a', 64), 'container_identity' => 'container-a', 'passed' => true,
    ];
    $service->recordAttestation($base + ['plane' => 'root', 'observed_at' => now()->subMinutes(20)->toIso8601String()]);
    $service->recordAttestation($base + ['plane' => 'root', 'observed_at' => now()->addMinutes(10)->toIso8601String()]);
    expect(\Illuminate\Support\Facades\DB::table('gowa_update_attestation_renewals')->count())->toBe(0);

    $different = array_merge($base, ['snapshot_hash' => str_repeat('b', 64)]);
    expect(fn () => $service->recordAttestation($different + ['plane' => 'root', 'observed_at' => now()->toIso8601String()]))
        ->toThrow(RuntimeException::class, 'attestation_conflict');
});

it('does not complete an operation without a quiescence proof', function (): void {
    $operation = gowaContractOperation('reconciling');
    $service = gowaContractService();
    $base = [
        'operation_id' => $operation->id, 'fencing_token' => 1, 'policy_version' => '1',
        'snapshot_hash' => str_repeat('a', 64), 'container_identity' => 'container-a',
        'passed' => true, 'observed_at' => now()->toIso8601String(),
    ];
    $service->recordAttestation($base + ['plane' => 'root']);
    $service->recordAttestation($base + ['plane' => 'runtime']);

    expect($service->commitIfQuiescent($operation, 'succeeded'))->toBeFalse()
        ->and($operation->fresh()->status)->toBe('reconciling');
});

it('does not complete a quiescent operation when the running digest does not match', function (): void {
    $operation = gowaContractOperation('reconciling');
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
            return ['container_identity' => 'container-a', 'digest' => 'sha256:'.str_repeat('b', 64), 'health' => 'healthy'];
        }

        public function isFresh(array $runtime): bool
        {
            return true;
        }
    };
    $service = new GowaUpdateService($catalog, $runner, $probe);
    $base = [
        'operation_id' => $operation->id, 'fencing_token' => 1, 'policy_version' => '1',
        'snapshot_hash' => str_repeat('a', 64), 'container_identity' => 'container-a',
        'passed' => true, 'observed_at' => now()->toIso8601String(),
    ];
    $service->recordAttestation($base + ['plane' => 'root']);
    $service->recordAttestation($base + ['plane' => 'runtime']);

    expect($service->commitIfQuiescent($operation, 'succeeded'))->toBeFalse()
        ->and($operation->fresh()->status)->toBe('reconciling');
});

it('consumes signed renewed evidence before committing a quiescent recovery', function (): void {
    $operation = gowaContractOperation('reconciling');
    $directory = sys_get_temp_dir().'/gowa-renewal-'.bin2hex(random_bytes(8));
    $evidenceDir = $directory.'/'.$operation->id.'/1';
    mkdir($evidenceDir, 0700, true);
    $evidencePath = $evidenceDir.'/1-mutation_observed.json';
    $publicKeyPath = $directory.'/evidence.pub';
    $keypair = sodium_crypto_sign_keypair();
    file_put_contents($publicKeyPath, base64_encode(sodium_crypto_sign_publickey($keypair)));
    chmod($publicKeyPath, 0600);
    $importer = new GowaEvidenceImporter($publicKeyPath, false);
    $runner = new class($evidencePath) implements GowaUpdateQuiescence, GowaUpdateRunner
    {
        public function __construct(private string $evidencePath) {}

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
            $noEvidence = ! is_file($this->evidencePath);

            return ['quiescent' => $noEvidence, 'systemd' => true, 'lock' => true, 'request' => true, 'evidence' => $noEvidence];
        }
    };
    $probe = new class implements GowaRuntimeProbe
    {
        public function current(): array
        {
            return ['container_identity' => 'container-a', 'digest' => 'sha256:'.str_repeat('a', 64), 'health' => 'healthy'];
        }

        public function isFresh(array $runtime): bool
        {
            return true;
        }
    };
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
    $service = new GowaUpdateService($catalog, $runner, $probe);
    $base = [
        'operation_id' => $operation->id, 'fencing_token' => 1, 'policy_version' => '1',
        'snapshot_hash' => str_repeat('a', 64), 'container_identity' => 'container-a', 'passed' => true,
    ];
    foreach (['root', 'runtime'] as $plane) {
        $service->recordAttestation($base + ['plane' => $plane, 'observed_at' => now()->subMinutes(20)->toIso8601String()]);
    }
    $proof = [
        'contract' => 'gowa-evidence-v1', 'operation_id' => $operation->id, 'fencing_token' => 1,
        'sequence' => 1, 'plane' => 'root', 'code' => 'mutation_observed',
        'occurred_at' => now()->toIso8601String(), 'snapshot_hash' => $base['snapshot_hash'],
        'container_identity' => $base['container_identity'],
        'attestation' => $base + ['plane' => 'root', 'observed_at' => now()->toIso8601String()],
    ];
    $signature = sodium_crypto_sign_detached($importer->canonicalJson($proof), sodium_crypto_sign_secretkey($keypair));
    file_put_contents($evidencePath, json_encode(['schema_version' => 1, 'payload' => $proof, 'signature' => base64_encode($signature)], JSON_THROW_ON_ERROR));
    chmod($evidencePath, 0600);
    config(['gowa-updater.evidence_root' => $directory, 'gowa-updater.request_root' => '']);

    try {
        file_put_contents($evidencePath.'.imported', 'existing archive');
        (new GowaUpdateReconciler($service, $importer))->reconcile();
        expect($operation->fresh()->status)->not->toBe('succeeded')
            ->and(is_file($evidencePath))->toBeTrue()
            ->and(file_get_contents($evidencePath.'.imported'))->toBe('existing archive');
        unlink($evidencePath.'.imported');

        (new GowaUpdateReconciler($service, $importer))->reconcile();
        expect($operation->fresh()->status)->toBe('succeeded')
            ->and(is_file($evidencePath))->toBeFalse()
            ->and(is_file($evidencePath.'.imported'))->toBeTrue()
            ->and($operation->attestations()->count())->toBe(2)
            ->and(\Illuminate\Support\Facades\DB::table('gowa_update_attestation_renewals')->count())->toBe(2);
    } finally {
        if (is_file($evidencePath)) {
            unlink($evidencePath);
        }
        if (is_file($evidencePath.'.imported')) {
            unlink($evidencePath.'.imported');
        }
        unlink($publicKeyPath);
        rmdir($evidenceDir);
        rmdir(dirname($evidenceDir));
        rmdir($directory);
    }
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

it('imports the first signed preparation evidence after stale reconciliation started', function (): void {
    $service = gowaContractService();
    $operation = gowaContractOperation('reconciling', 'lease_expired');

    $service->recordEvidence([
        'operation_id' => $operation->id,
        'fencing_token' => 1,
        'sequence' => 1,
        'code' => 'mutation_prepared',
        'occurred_at' => '2026-08-28T00:00:00+00:00',
        'snapshot_hash' => str_repeat('a', 64),
        'container_identity' => 'unknown',
        'plane' => 'root',
    ]);

    expect($operation->fresh()->status)->toBe('preparing')
        ->and($operation->fresh()->feature_snapshot['last_evidence_sequence']['root'])->toBe(1);
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

it('leaves forged recovery evidence untouched and does not complete the operation', function (): void {
    $operation = gowaContractOperation('reconciling');
    $operation->update(['lease_expires_at' => now()->addMinutes(10)]);
    $directory = sys_get_temp_dir().'/gowa-forged-'.bin2hex(random_bytes(8));
    $evidenceDir = $directory.'/'.$operation->id.'/1';
    mkdir($evidenceDir, 0700, true);
    $evidencePath = $evidenceDir.'/1-mutation_observed.json';
    $publicKeyPath = $directory.'/evidence.pub';
    $keypair = sodium_crypto_sign_keypair();
    file_put_contents($publicKeyPath, base64_encode(sodium_crypto_sign_publickey($keypair)));
    chmod($publicKeyPath, 0600);
    file_put_contents($evidencePath, json_encode([
        'schema_version' => 1,
        'payload' => ['contract' => 'gowa-evidence-v1'],
        'signature' => base64_encode(random_bytes(64)),
    ], JSON_THROW_ON_ERROR));
    chmod($evidencePath, 0600);
    config(['gowa-updater.evidence_root' => $directory, 'gowa-updater.request_root' => '']);

    try {
        (new GowaUpdateReconciler(gowaContractService(), new GowaEvidenceImporter($publicKeyPath, false)))->reconcile();
        expect($operation->fresh()->status)->toBe('reconciling')
            ->and(is_file($evidencePath))->toBeTrue()
            ->and(\Illuminate\Support\Facades\DB::table('gowa_update_attestation_renewals')->count())->toBe(0);
    } finally {
        unlink($evidencePath);
        unlink($publicKeyPath);
        rmdir($evidenceDir);
        rmdir(dirname($evidenceDir));
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
            return ['container_identity' => 'container-a', 'digest' => 'sha256:'.str_repeat('a', 64), 'health' => 'healthy'];
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
