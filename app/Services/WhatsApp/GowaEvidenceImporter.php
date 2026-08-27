<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use RuntimeException;

final class GowaEvidenceImporter
{
    public function __construct(private readonly string $publicKeyPath = '') {}

    /** @return array<string, mixed> */
    public function decode(string $path): array
    {
        if (! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new RuntimeException('evidence_unavailable');
        }

        $stat = lstat($path);
        if (! is_array($stat) || ($stat['size'] ?? PHP_INT_MAX) > 1_048_576 || (($stat['mode'] ?? 0) & 0o002) !== 0) {
            throw new RuntimeException('evidence_rejected');
        }

        try {
            $document = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('evidence_rejected');
        }
        if (! is_array($document) || ($document['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('evidence_rejected');
        }

        $payload = $document['payload'] ?? null;
        $signature = $document['signature'] ?? null;
        if (! is_array($payload) || ! is_string($signature) || ! preg_match('/^[A-Za-z0-9+\/=]+$/', $signature)) {
            throw new RuntimeException('evidence_rejected');
        }

        $canonical = $this->canonicalJson($payload);
        $publicKeyPath = $this->publicKeyPath ?: (string) config('gowa-updater.evidence_public_key_path', '');
        if ($publicKeyPath === '' || ! is_readable($publicKeyPath) || is_link($publicKeyPath)) {
            throw new RuntimeException('evidence_key_unavailable');
        }

        $publicKey = trim((string) file_get_contents($publicKeyPath));
        $decodedPublicKey = base64_decode($publicKey, true);
        if ($decodedPublicKey !== false && strlen($decodedPublicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $publicKey = $decodedPublicKey;
        }
        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('evidence_signature_invalid');
        }

        try {
            $verified = sodium_crypto_sign_verify_detached($decodedSignature, $canonical, $publicKey);
        } catch (\Throwable) {
            $verified = false;
        }
        if (! $verified) {
            throw new RuntimeException('evidence_signature_invalid');
        }

        $this->validatePayload($payload);

        return $payload;
    }

    /** @param array<string, mixed> $value */
    public function canonicalJson(array $value): string
    {
        return json_encode($this->sortKeys($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $payload */
    private function validatePayload(array $payload): void
    {
        if (($payload['contract'] ?? null) !== 'gowa-evidence-v1'
            || ! is_string($payload['operation_id'] ?? null)
            || ! preg_match('/^[0-9a-f-]{36}$/i', $payload['operation_id'])
            || ! is_int($payload['fencing_token'])
            || $payload['fencing_token'] < 1
            || ! is_int($payload['sequence'])
            || $payload['sequence'] < 1
            || ! is_string($payload['plane'] ?? null)
            || ! in_array($payload['plane'], ['root', 'runtime'], true)
            || ! is_string($payload['code'] ?? null)
            || ! preg_match('/^[a-z0-9_]{1,64}$/', $payload['code'])
            || ! is_string($payload['occurred_at'] ?? null)
            || ! $this->isDateTime($payload['occurred_at'])
            || ! is_string($payload['snapshot_hash'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/', $payload['snapshot_hash'])
            || ! is_string($payload['container_identity'] ?? null)
            || $payload['container_identity'] === '') {
            throw new RuntimeException('evidence_rejected');
        }
    }

    private function isDateTime(string $value): bool
    {
        try {
            new \DateTimeImmutable($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
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
