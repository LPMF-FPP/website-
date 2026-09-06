<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaUpdateQuiescence;
use App\Contracts\WhatsApp\GowaUpdateRunner;
use Illuminate\Support\Facades\Log;

final class SystemdGowaUpdateRunner implements GowaUpdateQuiescence, GowaUpdateRunner
{
    public function __construct(
        private readonly ?bool $enabled = null,
        private readonly ?bool $noSocketGate = null,
        private readonly ?string $submitHelper = null,
        private readonly ?string $runnerPath = null,
        private readonly ?string $capabilityManifest = null,
        private readonly ?string $sudoBinary = null,
    ) {}

    public function available(): bool
    {
        return ($this->enabled ?? (bool) config('gowa-updater.enabled', false))
            && ($this->noSocketGate ?? (bool) config('gowa-updater.no_socket_gate', false))
            && is_executable($this->submitHelper ?? (string) config('gowa-updater.submit_helper'))
            && $this->verifiedCapability();
    }

    public function quiescence(string $operationId): array
    {
        $unit = (string) config('gowa-updater.update_unit_prefix', 'lpmf-gowa-update@').strtolower($operationId).'.service';
        $systemd = false;
        $process = proc_open(['systemctl', 'show', '--no-pager', '--property=LoadState,ActiveState,SubState', $unit], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $systemd = ($status === 0
                && preg_match('/^ActiveState=inactive$/m', (string) $output) === 1
                && preg_match('/^SubState=dead$/m', (string) $output) === 1)
                || preg_match('/^LoadState=not-found$/m', (string) $output) === 1;
        }

        $lock = false;
        $lockPath = (string) config('gowa-updater.lock_path', '/run/lpmf/gowa-updater/update.lock');
        if ($lockPath !== '' && is_file($lockPath) && ! is_link($lockPath) && ($handle = fopen($lockPath, 'r')) !== false) {
            $lock = flock($handle, LOCK_EX | LOCK_NB);
            if ($lock) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }

        $operationRoot = rtrim((string) config('gowa-updater.request_root', '/var/lib/lpmf/gowa-updater/requests'), '/').'/'.strtolower($operationId);
        $request = ! is_link($operationRoot) && (
            ! is_dir($operationRoot)
            || (! is_file($operationRoot.'/request.pending') && ! is_file($operationRoot.'/request.json'))
        );
        $evidenceRoot = rtrim((string) config('gowa-updater.evidence_root', '/var/lib/lpmf/gowa-updater/evidence'), '/').'/'.strtolower($operationId);
        $evidence = ! is_link($evidenceRoot) && (! is_dir($evidenceRoot) || count(glob($evidenceRoot.'/*/*.json') ?: []) === 0);

        return [
            'quiescent' => $systemd && $lock && $request && $evidence,
            'systemd' => $systemd,
            'lock' => $lock,
            'request' => $request,
            'evidence' => $evidence,
        ];
    }

    /** @param array<string, scalar|null> $claim */
    public function dispatch(array $claim): bool
    {
        $operationId = $claim['operation_id'] ?? null;
        if (! $this->available() || ! is_string($operationId) || ! preg_match('/^[0-9a-f-]{36}$/i', $operationId)) {
            return false;
        }

        $helper = $this->submitHelper ?? (string) config('gowa-updater.submit_helper');
        $sudo = $this->sudoBinary ?? (string) (function_exists('config') ? config('gowa-updater.sudo_binary', '/usr/bin/sudo') : '/usr/bin/sudo');
        $process = proc_open([$sudo, '-n', $helper, strtolower($operationId)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            return false;
        }

        stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            Log::warning('GOWA submit helper rejected dispatch.', [
                'exit_code' => $exitCode,
                'reason' => $this->submitFailureReason((string) $error),
            ]);
        }

        return $exitCode === 0;
    }

    private function submitFailureReason(string $error): string
    {
        return match (true) {
            str_contains($error, 'no new privileges') => 'no_new_privileges',
            str_contains($error, 'effective uid is not 0') => 'setuid_unavailable',
            str_contains($error, 'a password is required') => 'password_required',
            str_contains($error, 'not allowed to execute') => 'sudo_policy_rejected',
            str_contains($error, 'unable to execute') => 'helper_execution_failed',
            default => 'submit_helper_failed',
        };
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
            || ($manifest['production_ready'] ?? false) !== true
            || ($manifest['contract'] ?? null) !== 'reconcile-first-v1'
            || ($manifest['capability_version'] ?? null) !== (string) (function_exists('app') && app()->bound('config') ? config('gowa-updater.required_capability_version', '1') : '1')) {
            return false;
        }

        return is_string($manifest['runner_sha256'] ?? null)
            && hash_equals($manifest['runner_sha256'], (string) hash_file('sha256', $runnerPath));
    }
}
