<?php

declare(strict_types=1);

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaReleasePreparer;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Jobs\DispatchGowaUpdateJob;
use App\Jobs\PrepareGowaReleaseJob;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\User;
use App\Services\WhatsApp\GowaUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeGowaReleasePreparer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v9.7.0', 'draft' => false, 'prerelease' => false,
        'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.7.0',
        'published_at' => '2026-10-20T00:00:00Z',
    ])]);
    $this->preparer = new FakeGowaReleasePreparer;
    $this->preparer->proof['state'] = 'unavailable';
    app()->instance(GowaReleasePreparer::class, $this->preparer);
    $current = ['release_id' => 'installed', 'version' => 'v9.3.0', 'digest' => 'sha256:'.str_repeat('a', 64)];
    $target = [
        'release_id' => 'release-a', 'version' => 'v9.7.0', 'digest' => 'sha256:'.str_repeat('b', 64),
        'upstream_tag' => 'v9.7.0', 'upstream_release_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.7.0',
    ];
    $catalog = Mockery::mock(GowaReleaseCatalog::class);
    $catalog->shouldReceive('generation')->andReturn('test-generation');
    $catalog->shouldReceive('approved')->andReturn([$current, $target]);
    $catalog->shouldReceive('find')->andReturnUsing(fn (string $id): ?array => $id === 'release-a' ? $target : null);
    app()->instance(GowaReleaseCatalog::class, $catalog);
    $runner = Mockery::mock(GowaUpdateRunner::class);
    $runner->shouldReceive('available')->andReturn(true);
    $runner->shouldNotReceive('dispatch');
    app()->instance(GowaUpdateRunner::class, $runner);
    $probe = Mockery::mock(GowaRuntimeProbe::class);
    $probe->shouldReceive('current')->andReturn([
        'health' => true, 'digest' => $current['digest'], 'container_identity' => 'container-test',
    ]);
    $probe->shouldReceive('isFresh')->andReturn(true);
    app()->instance(GowaRuntimeProbe::class, $probe);
});

function preparationOperator(bool $mutation = true): User
{
    $user = User::factory()->create(['role' => 'supervisor']);
    foreach ($mutation ? ['gowa-update.status', 'gowa-update.request'] : ['gowa-update.status'] as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => 'gowa-update', 'action' => 'test']);
        $user->permissions()->syncWithoutDetaching([$permission->id => ['granted' => true]]);
    }

    return $user;
}

it('requires authentication, mutation permission, confirmation and a stable version for preparation', function (): void {
    $this->postJson(route('whatsapp.updates.prepare'), [])->assertUnauthorized();
    $this->actingAs(preparationOperator(false))->postJson(route('whatsapp.updates.prepare'), [])->assertForbidden();
    $this->actingAs(preparationOperator())->postJson(route('whatsapp.updates.prepare'), [
        'version' => 'v9.7.0;whoami', 'confirmation' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors(['version', 'confirmation']);
    Queue::assertNothingPushed();
});

it('queues preparation on the dedicated worker without installing or mutating the catalog', function (): void {
    $user = preparationOperator();
    $this->actingAs($user)->postJson(route('whatsapp.updates.prepare'), [
        'version' => 'v9.7.0', 'confirmation' => true,
    ])->assertAccepted();
    Queue::assertPushed(PrepareGowaReleaseJob::class, fn ($job): bool => $job->version === 'v9.7.0'
        && $job->connection === 'gowa-maintenance' && $job->queue === 'gowa-maintenance');
    Queue::assertNotPushed(DispatchGowaUpdateJob::class);
    expect(ActivityLog::query()->where('action', 'GOWA_PREPARATION_REQUESTED')->exists())->toBeTrue();
});

it('fails closed when the preparation worker is unavailable or the release changed', function (): void {
    $this->preparer->enabled = false;
    $this->actingAs(preparationOperator())->postJson(route('whatsapp.updates.prepare'), [
        'version' => 'v9.7.0', 'confirmation' => true,
    ])->assertServiceUnavailable();
    $this->preparer->enabled = true;
    $this->postJson(route('whatsapp.updates.prepare'), [
        'version' => 'v9.6.0', 'confirmation' => true,
    ])->assertConflict()->assertJsonPath('code', 'release_not_latest');
    Queue::assertNothingPushed();
});

it('refuses installation without matching preparation even in the testing environment', function (): void {
    expect(fn () => app(GowaUpdateService::class)->create('release-a', (string) str()->uuid(), preparationOperator()->id))
        ->toThrow(RuntimeException::class, 'preparation_not_ready');
    $this->assertDatabaseCount('gowa_update_operations', 0);
    Queue::assertNothingPushed();
});

it('only enables installation for the prepared digest, generation, and current container', function (): void {
    $this->preparer->proof = array_replace($this->preparer->proof, ['state' => 'ready', 'digest' => 'sha256:'.str_repeat('b', 64)]);
    expect(app(GowaUpdateService::class)->status()['available'])->toBeTrue();
    foreach (['digest', 'catalog_generation', 'previous_digest', 'container_identity', 'release_id', 'state'] as $field) {
        $before = $this->preparer->proof;
        $this->preparer->proof[$field] = 'mismatch';
        expect(app(GowaUpdateService::class)->status()['available'])->toBeFalse();
        expect(fn () => app(GowaUpdateService::class)->create('release-a', (string) str()->uuid(), preparationOperator()->id))
            ->toThrow(RuntimeException::class, 'preparation_not_ready');
        $this->preparer->proof = $before;
    }
    Queue::assertNothingPushed();
});

it('creates one idempotent installation only after preparation and blocks further preparation while active', function (): void {
    $this->preparer->proof = array_replace($this->preparer->proof, ['state' => 'ready', 'digest' => 'sha256:'.str_repeat('b', 64)]);
    $user = preparationOperator();
    $action = (string) str()->uuid();
    $service = app(GowaUpdateService::class);
    $first = $service->create('release-a', $action, $user->id);
    $replayed = $service->create('release-a', $action, $user->id);
    expect($first->id)->toBe($replayed->id);
    Queue::assertPushed(DispatchGowaUpdateJob::class, 1);
    expect($service->status()['available'])->toBeFalse();
    $this->actingAs($user)->postJson(route('whatsapp.updates.prepare'), [
        'version' => 'v9.7.0', 'confirmation' => true,
    ])->assertConflict()->assertJsonPath('code', 'update_already_active');
    Queue::assertNotPushed(PrepareGowaReleaseJob::class);
});

it('keeps preparation within CSRF, audit, permission and throttle middleware', function (): void {
    $middleware = app('router')->getRoutes()->getByName('whatsapp.updates.prepare')->gatherMiddleware();
    expect($middleware)->toContain('web', 'audit.gowa-update', 'permission:gowa-update.request', 'throttle:gowa-update');
});

it('dispatches a unique preparation job without ever invoking the update runner', function (): void {
    $job = new PrepareGowaReleaseJob('v9.7.0', 1);
    $job->handle($this->preparer);
    expect($this->preparer->dispatched)->toBe(['v9.7.0'])
        ->and($job->uniqueId())->toBe('gowa-release-preparation');
    $this->preparer->enabled = false;
    expect(fn () => $job->handle($this->preparer))->toThrow(RuntimeException::class, 'preparation_dispatch_failed');
});
