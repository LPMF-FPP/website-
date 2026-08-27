<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaUpdateRunner;

final class SystemdGowaUpdateRunner implements GowaUpdateRunner
{
    public function __construct(
        private readonly ?bool $enabled = null,
        private readonly ?bool $noSocketGate = null,
        private readonly ?string $submitHelper = null,
        private readonly ?string $runnerPath = null,
        private readonly ?string $capabilityManifest = null,
    ) {}

    public function available(): bool
    {
        return ($this->enabled ?? (bool) config('gowa-updater.enabled', false))
            && ($this->noSocketGate ?? (bool) config('gowa-updater.no_socket_gate', false))
            && is_executable($this->submitHelper ?? (string) config('gowa-updater.submit_helper'))
            && $this->verifiedCapability();
    }

    public function dispatch(string $operationId): bool
    {
        if (! $this->available() || ! preg_match('/^[0-9a-f-]{36}$/i', $operationId)) {
            return false;
        }

        $helper = $this->submitHelper ?? (string) config('gowa-updater.submit_helper');
        $process = proc_open(['/usr/bin/sudo', '-n', $helper, strtolower($operationId)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            return false;
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return proc_close($process) === 0;
    }

    private function verifiedCapability(): bool
    {
        $manifestPath = $this->capabilityManifest ?? (string) config('gowa-updater.capability_manifest');
        $runnerPath = $this->runnerPath ?? (string) config('gowa-updater.runner_path');
        if (! is_readable($manifestPath) || ! is_executable($runnerPath)) {
            return false;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)
            || ($manifest['fully_implemented'] ?? false) !== true
            || ($manifest['contract'] ?? null) !== 'reconcile-first-v1'
            || ($manifest['capability_version'] ?? null) !== (string) config('gowa-updater.required_capability_version', '1')) {
            return false;
        }

        return is_string($manifest['runner_sha256'] ?? null)
            && hash_equals($manifest['runner_sha256'], (string) hash_file('sha256', $runnerPath))
            && $this->runnerReportsCapability($runnerPath);
    }

    private function runnerReportsCapability(string $runnerPath): bool
    {
        $process = proc_open([$runnerPath, '--capabilities'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            return false;
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $capability = json_decode((string) $stdout, true);

        return $status === 0
            && is_array($capability)
            && ($capability['contract'] ?? null) === 'reconcile-first-v1'
            && ($capability['fully_implemented'] ?? false) === true;
    }
}
