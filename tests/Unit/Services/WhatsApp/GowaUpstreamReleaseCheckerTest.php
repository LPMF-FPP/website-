<?php

declare(strict_types=1);

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use App\Services\WhatsApp\GowaUpstreamReleaseChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('gowa-updater.upstream_release_api', 'https://api.github.com/repos/aldinokemal/go-whatsapp-web-multidevice/releases/latest');
});

it('reports a newer GitHub release without approving it for execution', function (): void {
    Http::fake([
        'api.github.com/*' => Http::response([
            'tag_name' => 'v9.3.0',
            'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.3.0',
            'published_at' => '2026-08-29T16:01:08Z',
            'draft' => false,
            'prerelease' => false,
        ]),
    ]);

    $checker = new GowaUpstreamReleaseChecker(gowaUpstreamCatalog(), gowaUpstreamProbe());

    expect($checker->check())->toMatchArray([
        'current_version' => 'v9.2.2',
        'latest_version' => 'v9.3.0',
        'update_available' => true,
        'catalog_version_match' => false,
        'can_update' => false,
        'comparison_status' => 'compared',
        'blocked_reason' => 'release_not_approved',
    ]);
});

it('marks the latest release executable only when the signed catalog approves it', function (): void {
    Http::fake([
        'api.github.com/*' => Http::response([
            'tag_name' => 'v9.3.0',
            'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.3.0',
            'published_at' => '2026-08-29T16:01:08Z',
            'draft' => false,
            'prerelease' => false,
        ]),
    ]);
    $catalog = gowaUpstreamCatalog([[
        'release_id' => 'gowa-v9-3-0',
        'version' => 'v9.3.0',
        'digest' => 'sha256:'.str_repeat('b', 64),
        'upstream_tag' => 'v9.3.0',
        'upstream_release_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.3.0',
    ]]);

    expect((new GowaUpstreamReleaseChecker($catalog, gowaUpstreamProbe()))->check())->toMatchArray([
        'catalog_version_match' => true,
        'approved_release_id' => 'gowa-v9-3-0',
        'can_update' => true,
    ]);
});

it('does not offer another installation when the current digest maps to the latest release', function (): void {
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v9.2.2',
        'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.2.2',
        'published_at' => '2026-08-29T16:01:08Z',
        'draft' => false,
        'prerelease' => false,
    ])]);

    expect((new GowaUpstreamReleaseChecker(gowaUpstreamCatalog(), gowaUpstreamProbe()))->check())->toMatchArray([
        'current_version' => 'v9.2.2',
        'latest_version' => 'v9.2.2',
        'update_available' => false,
        'can_update' => false,
        'blocked_reason' => 'already_latest',
    ]);
});

it('does not claim a current version when runtime evidence is stale or unknown', function (): void {
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v9.3.0',
        'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.3.0',
        'published_at' => '2026-08-29T16:01:08Z',
        'draft' => false,
        'prerelease' => false,
    ])]);
    $probe = new class implements GowaRuntimeProbe
    {
        public function current(): array
        {
            return ['digest' => 'sha256:'.str_repeat('z', 64)];
        }

        public function isFresh(array $runtime): bool
        {
            return false;
        }
    };

    expect((new GowaUpstreamReleaseChecker(gowaUpstreamCatalog(), $probe))->check())->toMatchArray([
        'current_version' => null,
        'update_available' => false,
        'comparison_status' => 'runtime_stale',
        'blocked_reason' => 'runtime_stale',
    ]);
});

it('requires upstream provenance before reporting a catalog release as matched', function (): void {
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v9.3.0',
        'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.3.0',
        'published_at' => '2026-08-29T16:01:08Z',
        'draft' => false,
        'prerelease' => false,
    ])]);
    $catalog = gowaUpstreamCatalog([[
        'release_id' => 'gowa-v9-3-0',
        'version' => 'v9.3.0',
        'digest' => 'sha256:'.str_repeat('b', 64),
    ]]);

    expect((new GowaUpstreamReleaseChecker($catalog, gowaUpstreamProbe()))->check()['catalog_version_match'])->toBeFalse();
});

it('caches the upstream response while reporting when it was fetched', function (): void {
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v9.3.0',
        'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/v9.3.0',
        'published_at' => '2026-08-29T16:01:08Z',
        'draft' => false,
        'prerelease' => false,
    ])]);
    $checker = new GowaUpstreamReleaseChecker(gowaUpstreamCatalog(), gowaUpstreamProbe());

    $first = $checker->check();
    $second = $checker->check();

    Http::assertSentCount(1);
    expect($first['fetched_at'])->toBe($second['fetched_at'])
        ->and($first['checked_at'])->toBeString();
});

it('refreshes GitHub release data when an execution decision needs a fresh check', function (): void {
    $requests = 0;
    Http::fake(function () use (&$requests) {
        $requests++;
        $version = $requests === 1 ? 'v9.3.0' : 'v9.4.0';

        return Http::response([
            'tag_name' => $version,
            'html_url' => 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/'.$version,
            'published_at' => '2026-08-29T16:01:08Z',
            'draft' => false,
            'prerelease' => false,
        ]);
    });

    $checker = new GowaUpstreamReleaseChecker(gowaUpstreamCatalog(), gowaUpstreamProbe());
    $first = $checker->check();
    $fresh = $checker->check(true);

    Http::assertSentCount(2);
    expect($first['latest_version'])->toBe('v9.3.0')
        ->and($fresh['latest_version'])->toBe('v9.4.0')
        ->and($fresh['blocked_reason'])->toBe('release_not_approved');
});

it('rejects a non-GitHub endpoint before attaching a configured token', function (): void {
    config()->set('gowa-updater.upstream_release_api', 'https://example.test/releases/latest');
    config()->set('gowa-updater.upstream_github_token', 'test-token');
    Http::fake();

    expect(fn () => (new GowaUpstreamReleaseChecker(gowaUpstreamCatalog(), gowaUpstreamProbe()))->check())
        ->toThrow(RuntimeException::class, 'upstream_release_invalid_endpoint');
    Http::assertNothingSent();
});

/** @param array<int, array<string, mixed>> $additional */
function gowaUpstreamCatalog(array $additional = []): GowaReleaseCatalog
{
    $releases = array_merge([[
        'release_id' => 'gowa-v9-2-2',
        'version' => 'v9.2.2',
        'digest' => 'sha256:'.str_repeat('a', 64),
    ]], $additional);

    return new class($releases) implements GowaReleaseCatalog
    {
        public function __construct(private readonly array $releases) {}

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
            return 'test-generation';
        }
    };
}

function gowaUpstreamProbe(): GowaRuntimeProbe
{
    return new class implements GowaRuntimeProbe
    {
        public function current(): array
        {
            return ['digest' => 'sha256:'.str_repeat('a', 64)];
        }

        public function isFresh(array $runtime): bool
        {
            return true;
        }
    };
}
