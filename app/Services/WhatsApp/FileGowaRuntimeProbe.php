<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaRuntimeProbe;

final class FileGowaRuntimeProbe implements GowaRuntimeProbe
{
    public function __construct(private readonly string $path = '') {}

    public function current(): array
    {
        $path = $this->path ?: (string) config('gowa-updater.runtime_evidence_path');
        if ($path === '' || ! is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) && ($decoded['signature_valid'] ?? false) === true ? $decoded : [];
    }

    public function isFresh(array $runtime): bool
    {
        $observedAt = $runtime['observed_at'] ?? null;
        if (! is_string($observedAt)) {
            return false;
        }

        try {
            return now()->diffInSeconds(\Carbon\Carbon::parse($observedAt), false) <= 90;
        } catch (\Throwable) {
            return false;
        }
    }
}
