<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleaseCatalog;

final class FileGowaReleaseCatalog implements GowaReleaseCatalog
{
    private ?array $catalog = null;

    public function __construct(private readonly string $path = '') {}

    public function find(string $releaseId): ?array
    {
        foreach ($this->approved() as $release) {
            if (($release['release_id'] ?? null) === $releaseId) {
                return $release;
            }
        }

        return null;
    }

    public function approved(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $path = $this->path ?: (string) config('gowa-updater.catalog_path');
        if ($path === '' || ! is_readable($path)) {
            return $this->catalog = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ($decoded['signature_valid'] ?? false) !== true) {
            return $this->catalog = [];
        }

        $releases = $decoded['releases'] ?? [];
        if (! is_array($releases)) {
            return $this->catalog = [];
        }

        return $this->catalog = array_values(array_filter($releases, static fn ($release): bool => is_array($release)
            && ($release['approved'] ?? false) === true
            && ($release['revoked'] ?? false) !== true
            && is_string($release['release_id'] ?? null)
            && preg_match('/^sha256:[0-9a-f]{64}$/', (string) ($release['digest'] ?? '')) === 1
            && ! str_contains((string) ($release['image'] ?? ''), ':latest')));
    }

    public function generation(): ?string
    {
        $path = $this->path ?: (string) config('gowa-updater.catalog_path');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded)
            && ($decoded['signature_valid'] ?? false) === true
            && is_string($decoded['generation'] ?? null) ? $decoded['generation'] : null;
    }
}
