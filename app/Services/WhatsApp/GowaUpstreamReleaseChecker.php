<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use App\Contracts\WhatsApp\GowaRuntimeProbe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GowaUpstreamReleaseChecker
{
    private const UPSTREAM_HOST = 'api.github.com';

    private const UPSTREAM_PATH = '/repos/aldinokemal/go-whatsapp-web-multidevice/releases/latest';

    private const RELEASE_URL_PREFIX = 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/tag/';

    public function __construct(
        private readonly GowaReleaseCatalog $catalog,
        private readonly GowaRuntimeProbe $probe,
    ) {}

    /** @return array<string, mixed> */
    public function check(): array
    {
        $releases = $this->catalog->approved();
        $runtime = $this->probe->current();
        $runtimeFresh = $this->probe->isFresh($runtime);
        $currentVersion = $this->currentVersion($runtime, $releases);
        $latest = $this->latestRelease();
        $approved = collect($releases)->first(
            fn (array $release): bool => $this->normalizeVersion($release['version'] ?? null) === $latest['normalized_version']
                && ($release['upstream_tag'] ?? null) === $latest['version']
                && ($release['upstream_release_url'] ?? null) === $latest['release_url'],
        );
        $updateAvailable = $runtimeFresh && $currentVersion !== null
            && version_compare($latest['normalized_version'], $currentVersion, '>');

        return [
            'source' => 'github',
            'current_version' => $currentVersion === null ? null : 'v'.$currentVersion,
            'latest_version' => $latest['version'],
            'comparison_status' => ! $runtimeFresh ? 'runtime_stale' : ($currentVersion === null ? 'current_version_unknown' : 'compared'),
            'update_available' => $updateAvailable,
            'catalog_version_match' => is_array($approved),
            'approved_release_id' => is_array($approved) ? $approved['release_id'] : null,
            'can_update' => $updateAvailable && is_array($approved),
            'published_at' => $latest['published_at'],
            'release_url' => $latest['release_url'],
            'fetched_at' => $latest['fetched_at'],
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** @return array{version: string, normalized_version: string, published_at: string, release_url: string, fetched_at: string} */
    private function latestRelease(): array
    {
        $url = (string) config('gowa-updater.upstream_release_api');
        $ttl = max(30, (int) config('gowa-updater.upstream_cache_seconds', 300));

        return Cache::remember('gowa-updater:upstream:v2:'.sha1($url), $ttl, function () use ($url): array {
            $parts = parse_url($url);
            if (($parts['scheme'] ?? null) !== 'https'
                || ($parts['host'] ?? null) !== self::UPSTREAM_HOST
                || ($parts['path'] ?? null) !== self::UPSTREAM_PATH) {
                throw new RuntimeException('upstream_release_invalid_endpoint');
            }

            $request = Http::acceptJson()
                ->withHeaders(['User-Agent' => 'LPMF-LIMS-GOWA-Updater'])
                ->connectTimeout(2)
                ->timeout(5);
            $token = (string) config('gowa-updater.upstream_github_token', '');
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('upstream_release_unavailable');
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('upstream_release_invalid');
            }
            $normalized = $this->normalizeVersion($payload['tag_name'] ?? null);
            $releaseUrl = $payload['html_url'] ?? null;
            $publishedAt = $payload['published_at'] ?? null;
            if ($normalized === null
                || ($payload['draft'] ?? true) !== false
                || ($payload['prerelease'] ?? true) !== false
                || ! is_string($releaseUrl)
                || ! str_starts_with($releaseUrl, self::RELEASE_URL_PREFIX)
                || ! is_string($publishedAt)) {
                throw new RuntimeException('upstream_release_invalid');
            }

            try {
                $publishedAt = CarbonImmutable::parse($publishedAt)->toIso8601String();
            } catch (\Throwable) {
                throw new RuntimeException('upstream_release_invalid');
            }

            return [
                'version' => 'v'.$normalized,
                'normalized_version' => $normalized,
                'published_at' => $publishedAt,
                'release_url' => $releaseUrl,
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /** @param array<int, array<string, mixed>> $releases */
    private function currentVersion(array $runtime, array $releases): ?string
    {
        $digest = $runtime['digest'] ?? null;
        if (! is_string($digest)) {
            return null;
        }

        foreach ($releases as $release) {
            if (($release['digest'] ?? null) === $digest) {
                return $this->normalizeVersion($release['version'] ?? null);
            }
        }

        return null;
    }

    private function normalizeVersion(mixed $version): ?string
    {
        if (! is_string($version) || preg_match('/^v?(\d+\.\d+\.\d+)$/', trim($version), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
