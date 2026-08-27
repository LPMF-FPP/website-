<?php

use App\Models\GowaUpdateDispatchClaim;
use App\Models\User;
use App\Services\WhatsApp\GowaUpdateClaimService;
use App\Services\WhatsApp\GowaUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('creates one sanitized durable root-facing claim and replays it idempotently', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000021', $user->id);
    $claims = app(GowaUpdateClaimService::class);

    $first = $claims->claim($operation);
    $second = $claims->claim($operation);

    expect($first['operation_id'])->toBe($second['operation_id'])
        ->and($first['claim_nonce'])->toBe($second['claim_nonce'])
        ->and($first['replayed'])->toBeFalse()
        ->and($second['replayed'])->toBeTrue()
        ->and(GowaUpdateDispatchClaim::query()->where('operation_id', $operation->id)->count())->toBe(1)
        ->and(json_encode($first, JSON_THROW_ON_ERROR))->not->toContain('password')->not->toContain('secret');
});

it('rejects a claim after the scope fence or lease no longer matches', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000022', $user->id);
    $operation->update(['lease_expires_at' => now()->subSecond()]);

    expect(fn () => app(GowaUpdateClaimService::class)->claim($operation->fresh()))
        ->toThrow(RuntimeException::class, 'claim_rejected');
});

it('rejects a queued operation when its release is revoked before claim', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes();
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

    expect(fn () => app(GowaUpdateClaimService::class)->claim($operation->fresh()))
        ->toThrow(RuntimeException::class, 'release_not_allowed');
});

it('rejects a replay from a different lease owner without creating a second claim', function (): void {
    Queue::fake();
    bindGowaLifecycleFakes();
    $user = User::factory()->create();
    $operation = app(GowaUpdateService::class)->create('release-a', '00000000-0000-4000-8000-000000000024', $user->id);
    $claims = app(GowaUpdateClaimService::class);
    $claims->claim($operation, 'worker-a');

    expect(fn () => $claims->claim($operation->fresh(), 'worker-b'))
        ->toThrow(RuntimeException::class, 'claim_payload_mismatch')
        ->and(GowaUpdateDispatchClaim::query()->where('operation_id', $operation->id)->count())->toBe(1);
});
