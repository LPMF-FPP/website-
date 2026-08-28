<?php

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Jobs\DispatchGowaUpdateJob;
use App\Models\GowaUpdateAttestation;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdateScope;
use App\Models\User;
use App\Services\WhatsApp\GowaUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function bindGowaLifecycleFakes(bool $available = true): void
{
    app()->instance(GowaReleaseCatalog::class, new class implements GowaReleaseCatalog
    {
        public function find(string $releaseId): ?array
        {
            return $releaseId === 'release-a' ? ['release_id' => 'release-a', 'version' => '1.0.0', 'digest' => 'sha256:'.str_repeat('a', 64)] : null;
        }

        public function approved(): array
        {
            return [['release_id' => 'release-a', 'version' => '1.0.0', 'digest' => 'sha256:'.str_repeat('a', 64)]];
        }

        public function generation(): ?string
        {
            return 'test-generation';
        }
    });
    app()->instance(GowaUpdateRunner::class, new class($available) implements GowaUpdateRunner
    {
        public function __construct(private readonly bool $available) {}

        public function available(): bool
        {
            return $this->available;
        }

        public function dispatch(array $claim): bool
        {
            return $this->available;
        }
    });
    app()->instance(GowaRuntimeProbe::class, new class implements GowaRuntimeProbe
    {
        public function current(): array
        {
            return [
                'health' => 'healthy',
                'digest' => 'sha256:'.str_repeat('a', 64),
                'container_identity' => 'container-test',
                'observed_at' => now()->toIso8601String(),
            ];
        }

        public function isFresh(array $runtime): bool
        {
            return true;
        }
    });
}

it('rejects an action UUID reused with a different release payload', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes();
    $user = User::factory()->create();
    $service = app(GowaUpdateService::class);
    $service->create('release-a', '00000000-0000-4000-8000-000000000010', $user->id);

    expect(fn () => $service->create('different-release', '00000000-0000-4000-8000-000000000010', $user->id))
        ->toThrow(\RuntimeException::class, 'idempotency_payload_mismatch');
});

it('rejects a second active operation and clears stale operations safely', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes();
    $user = User::factory()->create();
    $service = app(GowaUpdateService::class);
    $first = $service->create('release-a', '00000000-0000-4000-8000-000000000011', $user->id);

    expect(fn () => $service->create('release-a', '00000000-0000-4000-8000-000000000012', $user->id))
        ->toThrow(\RuntimeException::class, 'update_already_active');

    $first->update(['lease_expires_at' => now()->subMinute()]);
    app(\App\Services\WhatsApp\GowaUpdateReconciler::class)->reconcile();

    expect($first->fresh()->status)->toBe('reconciling')
        ->and(GowaUpdateScope::query()->whereKey('gowa')->value('active_operation_id'))->toBe($first->id);
});

it('moves a queued operation to a safe terminal failure when dispatch fails', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes(false);
    $user = User::factory()->create();
    $operation = GowaUpdateOperation::create([
        'id' => (string) str()->uuid(),
        'scope' => 'gowa',
        'release_id' => 'release-a',
        'requested_version' => '1.0.0',
        'requested_digest' => 'sha256:'.str_repeat('a', 64),
        'status' => 'queued',
        'idempotency_key' => $user->id.':'.str()->uuid(),
        'client_action_uuid' => str()->uuid(),
        'fencing_token' => 1,
        'checkpoint' => 'queued',
        'requested_by' => $user->id,
        'heartbeat_at' => now(),
        'lease_expires_at' => now()->addMinutes(5),
    ]);
    GowaUpdateScope::query()->whereKey('gowa')->update(['active_operation_id' => $operation->id]);

    (new DispatchGowaUpdateJob($operation->id))->handle(app(GowaUpdateService::class), app(GowaUpdateRunner::class));

    expect($operation->fresh()->status)->toBe('failed')
        ->and(GowaUpdateScope::query()->whereKey('gowa')->value('active_operation_id'))->toBeNull();
});

it('requires matching fresh two-plane attestations before a terminal outcome', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000013', $user->id);
    $operation->update(['status' => 'verifying']);
    $common = [
        'operation_id' => $operation->id,
        'fencing_token' => $operation->fencing_token,
        'policy_version' => 'policy-1',
        'snapshot_hash' => str_repeat('c', 64),
        'container_identity' => 'container-test',
        'passed' => true,
        'observed_at' => now(),
    ];
    $service = app(GowaUpdateService::class);
    $service->recordAttestation(array_merge($common, ['plane' => 'root']));
    expect(fn () => $service->commitVerifiedOutcome($operation, 'succeeded'))
        ->toThrow(\RuntimeException::class, 'attestation_mismatch');

    $service->recordAttestation(array_merge($common, ['plane' => 'runtime']));
    $service->commitVerifiedOutcome($operation, 'succeeded');

    expect($operation->fresh()->status)->toBe('succeeded')
        ->and(GowaUpdateAttestation::query()->where('operation_id', $operation->id)->count())->toBe(2);
});
