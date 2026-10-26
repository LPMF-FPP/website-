<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleasePreparer;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use Carbon\CarbonImmutable;
use Symfony\Component\Process\Process;

final class SystemdGowaReleasePreparer implements GowaReleasePreparer
{
    public function __construct(
        private readonly GowaUpdateRunner $runner,
        private readonly bool $requireRootOwnedKey = true,
    ) {}

    public function available(): bool
    {
        if (! config('gowa-updater.preparation_enabled', false) || ! $this->runner->available()) {
            return false;
        }

        $manifestPath = (string) config('gowa-updater.capability_manifest');
        if (! $this->trustedFile($manifestPath)) {
            return false;
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        foreach (['preparer_path' => 'preparer_sha256', 'submit_helper' => 'submit_sha256'] as $pathKey => $hashKey) {
            $path = (string) config('gowa-updater.'.$pathKey);
            if (! $this->trustedFile($path) || ! is_executable($path)
                || ! is_string($manifest[$hashKey] ?? null)
                || ! hash_equals($manifest[$hashKey], (string) hash_file('sha256', $path))) {
                return false;
            }
        }

        return ($manifest['preparation_contract'] ?? null) === 'gowa-preparation-v1';
    }

    public function status(): array
    {
        $reader = new FileGowaRuntimeProbe(
            (string) config('gowa-updater.preparation_evidence_path'),
            (string) config('gowa-updater.evidence_public_key_path'),
            $this->requireRootOwnedKey,
        );
        $payload = $reader->current();
        if (($payload['contract'] ?? null) !== 'gowa-preparation-v1'
            || ! in_array($payload['state'] ?? null, ['preparing', 'ready', 'failed'], true)) {
            return ['state' => 'unavailable'];
        }

        try {
            $age = CarbonImmutable::parse($payload['observed_at'])->diffInSeconds(now(), false);
            if ($age < 0 || $age > 900) {
                return ['state' => 'expired'];
            }
        } catch (\Throwable) {
            return ['state' => 'unavailable'];
        }

        return array_intersect_key($payload, array_flip([
            'state', 'version', 'release_id', 'digest', 'previous_digest', 'container_identity',
            'catalog_generation', 'observed_at',
        ]));
    }

    public function dispatch(string $version): bool
    {
        if (! $this->available() || preg_match('/^v\d+\.\d+\.\d+$/D', $version) !== 1) {
            return false;
        }

        $process = new Process([
            (string) config('gowa-updater.sudo_binary'), '-n',
            (string) config('gowa-updater.submit_helper'), '--prepare', $version,
        ]);
        $process->setTimeout(15);
        $process->run();

        return $process->isSuccessful();
    }

    private function trustedFile(string $path): bool
    {
        if ($path === '' || ! is_file($path) || is_link($path) || ! is_readable($path)) {
            return false;
        }
        $stat = lstat($path);

        return is_array($stat) && $stat['uid'] === 0 && ($stat['mode'] & 0o022) === 0;
    }
}
