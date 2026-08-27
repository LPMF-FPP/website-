<?php

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Models\User;
use App\Services\WhatsApp\GowaUpdateClaimService;
use App\Services\WhatsApp\GowaUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function bindGowaClaimFakes(): void
{
    app()->instance(GowaReleaseCatalog::class, new class implements GowaReleaseCatalog
    {
        public function find(string $releaseId): ?array
        {
            return $releaseId === 'release-a' ? ['release_id' => 'release-a', 'version' => '1.0.0', 'digest' => 'sha256:'.str_repeat('a', 64), 'revocation_generation' => 'initial'] : null;
        }

        public function approved(): array
        {
            return [$this->find('release-a')];
        }

        public function generation(): ?string
        {
            return 'test-generation';
        }
    });
    app()->instance(GowaUpdateRunner::class, new class implements GowaUpdateRunner
    {
        public function available(): bool
        {
            return true;
        }

        public function dispatch(array $claim): bool
        {
            return true;
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
            ];
        }

        public function isFresh(array $runtime): bool
        {
            return true;
        }
    });
}

it('creates a root-facing request containing only the operation UUID', function (): void {
    Queue::fake();
    bindGowaClaimFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000021', $user->id);
    $claims = app(GowaUpdateClaimService::class);

    $first = $claims->requestRootClaim($operation);
    $second = $claims->requestRootClaim($operation);

    expect($first)->toBe(['operation_id' => $operation->id, 'replayed' => false])
        ->and($second)->toBe(['operation_id' => $operation->id, 'replayed' => false])
        ->and(json_encode($first, JSON_THROW_ON_ERROR))->not->toContain('password')->not->toContain('secret');
});

it('rejects a claim after the scope fence or lease no longer matches', function (): void {
    Queue::fake();
    bindGowaClaimFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000022', $user->id);
    $operation->update(['lease_expires_at' => now()->subSecond()]);

    expect(fn () => app(GowaUpdateClaimService::class)->requestRootClaim($operation->fresh()))
        ->toThrow(RuntimeException::class, 'claim_rejected');
});

it('rejects a queued operation when its release is revoked before claim', function (): void {
    Queue::fake();
    bindGowaClaimFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000023', $user->id);
    app()->instance(\App\Contracts\WhatsApp\GowaReleaseCatalog::class, new class implements \App\Contracts\WhatsApp\GowaReleaseCatalog
    {
        public function find(string $releaseId): ?array
        {
            return null;
        }

        public function approved(): array
        {
            return [];
        }

        public function generation(): ?string
        {
            return 'test-generation-2';
        }
    });

    expect(fn () => app(GowaUpdateClaimService::class)->requestRootClaim($operation->fresh()))
        ->toThrow(RuntimeException::class, 'release_not_allowed');
});

it('rejects a root request from a non-maintenance owner', function (): void {
    Queue::fake();
    bindGowaClaimFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000024', $user->id);
    $claims = app(GowaUpdateClaimService::class);

    expect(fn () => $claims->requestRootClaim($operation->fresh(), 'worker-a'))
        ->toThrow(RuntimeException::class, 'claim_rejected');
});
