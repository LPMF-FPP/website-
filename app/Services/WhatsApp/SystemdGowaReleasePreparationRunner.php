<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\GowaReleasePreparationRunner;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class SystemdGowaReleasePreparationRunner implements GowaReleasePreparationRunner
{
    public function available(): bool
    {
        $databaseNameFile = (string) config('gowa-updater.database_name_file');
        if (! is_file($databaseNameFile) || is_link($databaseNameFile)) {
            return false;
        }
        $databaseStat = lstat($databaseNameFile);
        if (! is_array($databaseStat)
            || ($databaseStat['uid'] ?? -1) !== 0
            || (($databaseStat['mode'] ?? 0) & 0o777) !== 0o600) {
            return false;
        }

        if (! (bool) config('gowa-updater.enabled', false)
            || ! (bool) config('gowa-updater.no_socket_gate', false)
            || ! is_executable((string) config('gowa-updater.preparation_helper'))
            || ! is_executable('/usr/bin/timeout')
        ) {
            return false;
        }

        try {
            $capability = $this->run('--prepare-capabilities');
        } catch (\Throwable) {
            return false;
        }

        return ($capability['contract'] ?? null) === 'gowa-prepare-latest-v1'
            && ($capability['preparation_ready'] ?? false) === true;
    }

    public function prepareLatest(): array
    {
        if (! $this->available()) {
            throw new RuntimeException('preparation_runner_unavailable');
        }

        $capability = $this->run('--prepare-capabilities');
        if (($capability['contract'] ?? null) !== 'gowa-prepare-latest-v1'
            || ($capability['preparation_ready'] ?? false) !== true) {
            throw new RuntimeException('preparation_runner_unavailable');
        }

        $result = $this->run('--prepare-latest');
        foreach (['release_id', 'version', 'digest', 'catalog_generation'] as $field) {
            if (! is_string($result[$field] ?? null) || $result[$field] === '') {
                throw new RuntimeException('preparation_result_invalid');
            }
        }
        if (preg_match('/^sha256:[0-9a-f]{64}$/', $result['digest']) !== 1) {
            throw new RuntimeException('preparation_result_invalid');
        }

        return array_intersect_key($result, array_flip(['release_id', 'version', 'digest', 'catalog_generation']));
    }

    /** @return array<string, mixed> */
    private function run(string $action): array
    {
        $sudo = (string) config('gowa-updater.sudo_binary', '/usr/bin/sudo');
        $helper = (string) config('gowa-updater.preparation_helper');
        $timeout = (string) max(30, (int) config('gowa-updater.preparation_timeout_seconds', 780));
        $process = proc_open(['/usr/bin/timeout', '--foreground', $timeout, $sudo, '-n', $helper, $action], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('preparation_runner_unavailable');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $result = json_decode((string) $output, true);

        if ($exitCode !== 0 || ! is_array($result)) {
            Log::warning('GOWA release preparation helper failed.', [
                'exit_code' => $exitCode,
                'reason' => $this->safeFailureReason((string) $error),
            ]);
            throw new RuntimeException('preparation_failed');
        }

        return $result;
    }

    private function safeFailureReason(string $error): string
    {
        return match (true) {
            str_contains($error, 'a password is required') => 'password_required',
            str_contains($error, 'not allowed to execute') => 'sudo_policy_rejected',
            str_contains($error, 'unable to execute') => 'helper_execution_failed',
            default => 'preparation_helper_failed',
        };
    }
}
