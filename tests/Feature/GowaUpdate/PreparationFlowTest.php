<?php

declare(strict_types=1);

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaReleasePreparationRunner;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use App\Jobs\PrepareGowaReleaseJob;
use App\Models\GowaUpdateOperation;
use App\Models\GowaUpdatePreparation;
use App\Models\Permission;
use App\Models\User;
use App\Services\WhatsApp\GowaUpdatePreparationService;
use App\Services\WhatsApp\GowaUpdateService;
use App\Services\WhatsApp\GowaUpstreamReleaseChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('gowa-updater.upstream_release_api', 'https://api.github.com/repos/aldinokemal/go-whatsapp-web-multidevice/releases/latest');
    Queue::fake();
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v9.5.0',
        'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.5.0',
        'published_at' => '2026-09-25T00:58:33Z',
        'draft' => false,
        'prerelease' => false,
    ])]);
});

function gowaPreparationFixture(): array
{
    $catalog = new class implements GowaReleaseCatalog
    {
        public array $releases;

        public string $catalogGeneration = 'generation-1';

        public function __construct()
        {
            $this->releases = [[
                'release_id' => 'gowa-v9-3-0',
                'version' => 'v9.3.0',
                'digest' => 'sha256:'.str_repeat('a', 64),
                'upstream_tag' => 'v9.3.0',
                'upstream_release_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.3.0',
                'revocation_generation' => 'rev-1',
            ]];
        }

        public function find(string $releaseId): ?array
        {
            return collect($this->releases)->firstWhere('release_id', $releaseId);
        }

        public function approved(): array
        {
            return $this->releases;
        }

        public function generation(): ?string
        {
            return $this->catalogGeneration;
        }
    };

    $probe = new class implements GowaRuntimeProbe
    {
        public array $runtime;

        public function __construct()
        {
            $this->runtime = [
                'observed_at' => null,
                'container_identity' => 'container-v9-3-0',
                'digest' => 'sha256:'.str_repeat('a', 64),
                'health' => 'healthy',
            ];
        }

        public function current(): array
        {
            $this->runtime['observed_at'] = now()->toIso8601String();

            return $this->runtime;
        }

        public function isFresh(array $runtime): bool
        {
            return isset($runtime['observed_at']);
        }
    };

    $runner = new class($catalog) implements GowaReleasePreparationRunner
    {
        public function __construct(private readonly GowaReleaseCatalog $catalog) {}

        public function available(): bool
        {
            return true;
        }

        public function prepareLatest(): array
        {
            $release = [
                'release_id' => 'gowa-v9-5-0',
                'version' => 'v9.5.0',
                'digest' => 'sha256:'.str_repeat('b', 64),
                'upstream_tag' => 'v9.5.0',
                'upstream_release_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.5.0',
                'revocation_generation' => 'rev-1',
            ];
            $this->catalog->releases[] = $release;
            $this->catalog->catalogGeneration = 'generation-2';

            return [
                'release_id' => $release['release_id'],
                'version' => $release['version'],
                'digest' => $release['digest'],
                'catalog_generation' => $this->catalog->generation(),
            ];
        }
    };

    $checker = new GowaUpstreamReleaseChecker($catalog, $probe);

    return compact('catalog', 'probe', 'runner', 'checker');
}

it('prepares a newer release without creating an installation operation', function (): void {
    $fixture = gowaPreparationFixture();
    $user = User::factory()->create();
    $service = new GowaUpdatePreparationService(
        $fixture['checker'],
        $fixture['runner'],
        $fixture['probe'],
        $fixture['catalog'],
    );

    $preparation = $service->startLatest('00000000-0000-4000-8000-000000000101', $user->id);

    expect($preparation->status)->toBe('queued')
        ->and($preparation->requested_version)->toBe('v9.5.0')
        ->and(GowaUpdateOperation::query()->count())->toBe(0);
    Queue::assertPushed(PrepareGowaReleaseJob::class);
});

it('queues preparation only for a signed-in user with update request permission', function (): void {
    $fixture = gowaPreparationFixture();
    $user = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $permission = Permission::firstOrCreate(['name' => 'gowa-update.request'], [
        'display_name' => 'Request GOWA update',
        'module' => 'gowa-update',
        'action' => 'request',
    ]);
    $user->permissions()->syncWithoutDetaching([$permission->id => ['granted' => true]]);
    app()->instance(GowaReleaseCatalog::class, $fixture['catalog']);
    app()->instance(GowaRuntimeProbe::class, $fixture['probe']);
    app()->instance(GowaReleasePreparationRunner::class, $fixture['runner']);

    $this->actingAs($user)
        ->postJson(route('whatsapp.updates.prepare'), [
            'action_uuid' => '00000000-0000-4000-8000-000000000104',
        ])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.version', 'v9.5.0');

    expect(GowaUpdatePreparation::query()->where('requested_by', $user->id)->count())->toBe(1)
        ->and(GowaUpdateOperation::query()->count())->toBe(0);
    Queue::assertPushed(PrepareGowaReleaseJob::class);
});

it('marks preparation ready only after release, catalog, and unchanged runtime match', function (): void {
    $fixture = gowaPreparationFixture();
    $user = User::factory()->create();
    $service = new GowaUpdatePreparationService(
        $fixture['checker'],
        $fixture['runner'],
        $fixture['probe'],
        $fixture['catalog'],
    );
    $preparation = $service->startLatest('00000000-0000-4000-8000-000000000102', $user->id);

    (new PrepareGowaReleaseJob($preparation->id))->handle(
        $fixture['runner'],
        $fixture['probe'],
        $fixture['checker'],
    );

    expect($preparation->fresh()->status)->toBe('ready')
        ->and($preparation->fresh()->digest)->toBe('sha256:'.str_repeat('b', 64))
        ->and($preparation->fresh()->catalog_generation)->toBe('generation-2')
        ->and($service->assertInstallable($preparation->id, $user->id)->safeProjection()['ready'])->toBeTrue()
        ->and(GowaUpdateOperation::query()->count())->toBe(0);
});

it('fails closed if the runtime changes while release preparation is in progress', function (): void {
    $fixture = gowaPreparationFixture();
    $user = User::factory()->create();
    $service = new GowaUpdatePreparationService(
        $fixture['checker'],
        $fixture['runner'],
        $fixture['probe'],
        $fixture['catalog'],
    );
    $preparation = $service->startLatest('00000000-0000-4000-8000-000000000103', $user->id);
    $fixture['probe']->runtime['container_identity'] = 'unexpected-container';

    (new PrepareGowaReleaseJob($preparation->id))->handle(
        $fixture['runner'],
        $fixture['probe'],
        $fixture['checker'],
    );

    expect($preparation->fresh()->status)->toBe('failed')
        ->and($preparation->fresh()->failure_code)->toBe('preparation_evidence_mismatch')
        ->and(GowaUpdateOperation::query()->count())->toBe(0);
});

it('refuses a direct production installation request without a preparation record', function (): void {
    $fixture = gowaPreparationFixture();
    $runner = new class implements GowaUpdateRunner
    {
        public function available(): bool
        {
            return true;
        }

        public function dispatch(array $claim): bool
        {
            return true;
        }
    };
    $service = new GowaUpdateService($fixture['catalog'], $runner, $fixture['probe'], $fixture['checker']);
    $environment = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        expect(fn () => $service->create('gowa-v9-5-0', '00000000-0000-4000-8000-000000000104', 1))
            ->toThrow(RuntimeException::class, 'preparation_not_ready');
    } finally {
        app()->detectEnvironment(static fn (): string => $environment);
    }

    expect(GowaUpdateOperation::query()->count())->toBe(0);
});
