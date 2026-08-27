<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleaseCatalog;
use RuntimeException;

final class FileGowaReleaseCatalog implements GowaReleaseCatalog
{
    private ?array $catalog = null;

    public function __construct(
        private readonly string $path = '',
        private readonly string $publicKeyPath = '',
        private readonly bool $requireRootOwnedKey = true,
    ) {}

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

        $raw = (string) file_get_contents($path);
        $decoded = $this->decodeCatalog($raw);
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

        $decoded = $this->decodeCatalog((string) file_get_contents($path));

        return $this->isValidCatalog($decoded) ? $decoded['generation'] : null;
    }

    public function canonicalPayload(array $catalog): string
    {
        unset($catalog['signature'], $catalog['signature_valid']);

        return json_encode($this->sortKeys($catalog), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function decodeCatalog(string $raw): mixed
    {
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if ($this->hasDuplicateObjectKeys($raw)) {
            return null;
        }

        return $decoded;
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

        if (! $this->verifySignature($catalog)) {
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

    private function verifySignature(array $catalog): bool
    {
        $path = $this->publicKeyPath ?: (string) config('gowa-updater.catalog_public_key_path', '');
        if ($path === '' || ! is_file($path) || is_link($path) || ! is_readable($path)) {
            return false;
        }

        $stat = lstat($path);
        if (! is_array($stat) || (($stat['mode'] ?? 0) & 0o022) !== 0 || ($this->requireRootOwnedKey && ($stat['uid'] ?? -1) !== 0)) {
            return false;
        }

        $publicKey = base64_decode(trim((string) file_get_contents($path)), true);
        $signature = base64_decode($catalog['signature']['value'], true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $this->canonicalPayload($catalog), $publicKey);
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasDuplicateObjectKeys(string $json): bool
    {
        $offset = 0;

        try {
            $this->parseJsonValue($json, $offset);
            while (isset($json[$offset]) && ctype_space($json[$offset])) {
                $offset++;
            }

            return $offset !== strlen($json);
        } catch (RuntimeException) {
            return true;
        }
    }

    private function parseJsonValue(string $json, int &$offset): mixed
    {
        while (isset($json[$offset]) && ctype_space($json[$offset])) {
            $offset++;
        }

        if (($json[$offset] ?? null) === '{') {
            $offset++;
            $keys = [];
            while (true) {
                while (isset($json[$offset]) && ctype_space($json[$offset])) {
                    $offset++;
                }
                if (($json[$offset] ?? null) === '}') {
                    $offset++;

                    return false;
                }
                $key = $this->parseJsonString($json, $offset);
                if (isset($keys[$key])) {
                    return true;
                }
                $keys[$key] = true;
                while (isset($json[$offset]) && ctype_space($json[$offset])) {
                    $offset++;
                }
                if (($json[$offset] ?? null) !== ':') {
                    throw new RuntimeException('invalid_json');
                }
                $offset++;
                if ($this->parseJsonValue($json, $offset) === true) {
                    return true;
                }
                while (isset($json[$offset]) && ctype_space($json[$offset])) {
                    $offset++;
                }
                if (($json[$offset] ?? null) === '}') {
                    $offset++;

                    return false;
                }
                if (($json[$offset] ?? null) !== ',') {
                    throw new RuntimeException('invalid_json');
                }
                $offset++;
            }
        }

        if (($json[$offset] ?? null) === '[') {
            $offset++;
            while (true) {
                while (isset($json[$offset]) && ctype_space($json[$offset])) {
                    $offset++;
                }
                if (($json[$offset] ?? null) === ']') {
                    $offset++;

                    return false;
                }
                if ($this->parseJsonValue($json, $offset) === true) {
                    return true;
                }
                while (isset($json[$offset]) && ctype_space($json[$offset])) {
                    $offset++;
                }
                if (($json[$offset] ?? null) === ']') {
                    $offset++;

                    return false;
                }
                if (($json[$offset] ?? null) !== ',') {
                    throw new RuntimeException('invalid_json');
                }
                $offset++;
            }
        }

        if (($json[$offset] ?? null) === '"') {
            $this->parseJsonString($json, $offset);

            return false;
        }

        $start = $offset;
        while (isset($json[$offset]) && ! str_contains(" \t\r\n,]}", $json[$offset])) {
            $offset++;
        }
        if ($start === $offset) {
            throw new RuntimeException('invalid_json');
        }

        return false;
    }

    private function parseJsonString(string $json, int &$offset): string
    {
        $start = $offset;
        if (($json[$offset] ?? null) !== '"') {
            throw new RuntimeException('invalid_json');
        }
        $offset++;
        $escaped = false;
        while (isset($json[$offset])) {
            $character = $json[$offset++];
            if ($escaped) {
                $escaped = false;
            } elseif ($character === '\\') {
                $escaped = true;
            } elseif ($character === '"') {
                $value = json_decode(substr($json, $start, $offset - $start), true, 1, JSON_THROW_ON_ERROR);

                return is_string($value) ? $value : throw new RuntimeException('invalid_json');
            }
        }

        throw new RuntimeException('invalid_json');
    }

    private function token(mixed $value, int $maxLength): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= $maxLength && preg_match('/^[A-Za-z0-9._:-]+$/', $value) === 1;
    }

    private function repository(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 255 && preg_match('~^[A-Za-z0-9._/-]+$~', $value) === 1;
    }

    /** @param array<string, mixed> $value */
    private function sortKeys(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
