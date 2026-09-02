<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaRuntimeProbe;

final class FileGowaRuntimeProbe implements GowaRuntimeProbe
{
    public function __construct(
        private readonly string $path = '',
        private readonly string $publicKeyPath = '',
        private readonly bool $requireRootOwnedKey = true,
    ) {}

    public function current(): array
    {
        $path = $this->path ?: (string) config('gowa-updater.runtime_evidence_path');
        if ($path === '' || ! is_file($path) || is_link($path) || ! is_readable($path)) {
            return [];
        }

        $stat = lstat($path);
        if (! is_array($stat)
            || (($stat['mode'] ?? 0) & 0o022) !== 0
            || ($this->requireRootOwnedKey && ($stat['uid'] ?? -1) !== 0)
            || ($stat['size'] ?? PHP_INT_MAX) > 1_048_576) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $payload = is_array($decoded) && ($decoded['payload'] ?? null) instanceof \stdClass
            ? (array) $decoded['payload']
            : ($decoded['payload'] ?? null);
        $signature = $decoded['signature'] ?? null;
        $publicKeyPath = $this->publicKeyPath ?: (string) config('gowa-updater.evidence_public_key_path', '');
        if (($decoded['schema_version'] ?? null) !== 1
            || ! is_array($payload)
            || ! is_string($signature)
            || $publicKeyPath === ''
            || ! is_file($publicKeyPath)
            || is_link($publicKeyPath)
            || ! is_readable($publicKeyPath)) {
            return [];
        }

        $keyStat = lstat($publicKeyPath);
        if (! is_array($keyStat)
            || (($keyStat['mode'] ?? 0) & 0o022) !== 0
            || ($this->requireRootOwnedKey && ($keyStat['uid'] ?? -1) !== 0)) {
            return [];
        }

        $publicKey = base64_decode(trim((string) file_get_contents($publicKeyPath)), true);
        $decodedSignature = base64_decode($signature, true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || $decodedSignature === false || strlen($decodedSignature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return [];
        }

        try {
            if (! sodium_crypto_sign_verify_detached($decodedSignature, $this->canonicalJson($payload), $publicKey)) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        return is_string($payload['observed_at'] ?? null)
            && is_string($payload['container_identity'] ?? null)
            && $payload['container_identity'] !== ''
            ? $payload
            : [];
    }

    public function isFresh(array $runtime): bool
    {
        $observedAt = $runtime['observed_at'] ?? null;
        if (! is_string($observedAt)) {
            return false;
        }

        try {
            $age = \Carbon\Carbon::parse($observedAt)->diffInSeconds(now(), false);

            return $age >= 0 && $age <= 90;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->sortKeys($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
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
