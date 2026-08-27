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
        if (! $this->isValidCatalog($decoded)) {
            return $this->catalog = [];
        }

        $releases = $decoded['releases'] ?? [];
        if (! is_array($releases)) {
            return $this->catalog = [];
        }

        return $this->catalog = array_values(array_filter($releases, function (array $release) use ($decoded): bool {
            return $release['approved'] === true
                && $release['revoked'] === false
                && $release['revocation_generation'] === $decoded['revocation_generation'];
        }));
    }

    public function generation(): ?string
    {
        $path = $this->path ?: (string) config('gowa-updater.catalog_path');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->isValidCatalog($decoded) ? $decoded['generation'] : null;
    }

    private function isValidCatalog(mixed $catalog): bool
    {
        $required = ['schema_version', 'signature_valid', 'generation', 'revocation_generation', 'approved_registry', 'approved_repository', 'platform', 'signature', 'releases'];
        if (! is_array($catalog)
            || array_diff(array_keys($catalog), $required) !== []
            || array_diff($required, array_keys($catalog)) !== []
            || $catalog['schema_version'] !== 1
            || $catalog['signature_valid'] !== true
            || ! $this->token($catalog['generation'] ?? null, 128)
            || ! $this->token($catalog['revocation_generation'] ?? null, 128)
            || ! $this->token($catalog['approved_registry'] ?? null, 255)
            || ! $this->repository($catalog['approved_repository'] ?? null)
            || $catalog['platform'] !== 'linux/amd64'
            || ! is_array($catalog['signature'] ?? null)
            || array_diff(array_keys($catalog['signature']), ['algorithm', 'key_id', 'value']) !== []
            || array_diff(['algorithm', 'key_id', 'value'], array_keys($catalog['signature'])) !== []
            || $catalog['signature']['algorithm'] !== 'ed25519'
            || ! $this->token($catalog['signature']['key_id'] ?? null, 128)
            || ! is_string($catalog['signature']['value'])
            || preg_match('/^[A-Za-z0-9+\/=]{16,}$/', $catalog['signature']['value']) !== 1
            || ! is_array($catalog['releases'])) {
            return false;
        }

        $ids = [];
        foreach ($catalog['releases'] as $release) {
            $releaseFields = ['release_id', 'version', 'image', 'digest', 'approved', 'revoked', 'revocation_generation'];
            if (! is_array($release)
                || array_diff(array_keys($release), $releaseFields) !== []
                || array_diff($releaseFields, array_keys($release)) !== []
                || ! $this->token($release['release_id'] ?? null, 128)
                || ! $this->token($release['version'] ?? null, 128)
                || ! is_string($release['digest'])
                || preg_match('/^sha256:[0-9a-f]{64}$/', $release['digest']) !== 1
                || ! is_string($release['image'])
                || $release['image'] !== $catalog['approved_repository'].'@'.$release['digest']
                || $release['approved'] !== true
                || ! is_bool($release['revoked'])
                || ! is_string($release['revocation_generation'])
                || isset($ids[$release['release_id']])) {
                return false;
            }
            $ids[$release['release_id']] = true;
        }

        return true;
    }

    private function token(mixed $value, int $maxLength): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= $maxLength && preg_match('/^[A-Za-z0-9._:-]+$/', $value) === 1;
    }

    private function repository(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 255 && preg_match('~^[A-Za-z0-9._/-]+$~', $value) === 1;
    }
}
