<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class BackupService
{
    public function createDatabaseDump(string $outputPath): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $driver = $config['driver'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];
        $host = $config['host'];
        $port = $config['port'] ?? ($driver === 'mysql' ? 3306 : 5432);

        $dumpFile = $outputPath.'/db.sql';
        $gzipFile = $dumpFile.'.gz';

        if ($driver === 'mysql') {
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s --port=%d %s > %s',
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($host),
                $port,
                escapeshellarg($database),
                escapeshellarg($dumpFile)
            );
        } elseif ($driver === 'pgsql') {
            $command = sprintf(
                'PGPASSWORD=%s pg_dump --username=%s --host=%s --port=%d %s > %s',
                escapeshellarg($password),
                escapeshellarg($username),
                escapeshellarg($host),
                $port,
                escapeshellarg($database),
                escapeshellarg($dumpFile)
            );
        } else {
            throw new \RuntimeException("Unsupported database driver: {$driver}");
        }

        $result = Process::run($command);

        if ($result->failed()) {
            throw new \RuntimeException('Database dump failed: '.$result->errorOutput());
        }

        exec("gzip {$dumpFile}");

        if (! file_exists($gzipFile)) {
            throw new \RuntimeException('Failed to compress database dump');
        }

        return [
            'path' => $gzipFile,
            'size' => filesize($gzipFile),
        ];
    }

    public function createStorageArchive(string $outputPath): array
    {
        $storagePath = storage_path('app');
        $archivePath = $outputPath.'/storage.tar.gz';

        $excludes = [
            'backups',
            'public/.gitignore',
            '.gitignore',
        ];

        foreach ([
            'private/qmh',
            'tmp/qmh/.config',
            'tmp/qmh/.cache',
        ] as $candidate) {
            $absoluteCandidatePath = $storagePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $candidate);

            if (is_dir($absoluteCandidatePath) && ! is_readable($absoluteCandidatePath)) {
                $excludes[] = $candidate;
            }
        }

        $excludeArgs = implode(' ', array_map(
            fn ($ex) => "--exclude='{$ex}'",
            array_unique($excludes)
        ));

        $command = sprintf(
            'tar -czf %s --ignore-failed-read -C %s %s .',
            escapeshellarg($archivePath),
            escapeshellarg($storagePath),
            $excludeArgs
        );

        $result = Process::run($command);
        $warnings = $this->extractTarWarnings($result->output()."\n".$result->errorOutput());

        if ($result->failed() && ! $this->isIgnorableTarFailure($result->errorOutput(), $archivePath)) {
            throw new \RuntimeException('Storage archive failed: '.$result->errorOutput());
        }

        return [
            'path' => $archivePath,
            'size' => filesize($archivePath),
            'warnings' => $warnings,
        ];
    }

    public function generateManifest(string $outputPath, array $files, array $meta = []): array
    {
        $manifest = [
            'created_at' => now()->toIso8601String(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'git_commit' => $this->getGitCommit(),
            'files' => [],
            'meta' => $meta,
        ];

        foreach ($files as $label => $filePath) {
            if (file_exists($filePath)) {
                $manifest['files'][$label] = [
                    'path' => basename($filePath),
                    'size' => filesize($filePath),
                    'sha256' => hash_file('sha256', $filePath),
                ];
            }
        }

        $manifestPath = $outputPath.'/manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        return [
            'path' => $manifestPath,
            'data' => $manifest,
        ];
    }

    public function getGitCommit(): ?string
    {
        try {
            $result = Process::run('git rev-parse HEAD');

            return $result->successful() ? trim($result->output()) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function createBackupDirectory(string $mode = 'emergency'): string
    {
        $timestamp = now()->format('Ymd_His');
        $dir = storage_path("app/backups/{$mode}/{$timestamp}");

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function cleanupOldBackups(int $retentionDays = 14, string $mode = 'emergency'): int
    {
        $cutoffDate = now()->subDays($retentionDays);
        $basePath = storage_path("app/backups/{$mode}");

        if (! is_dir($basePath)) {
            return 0;
        }

        $deleted = 0;
        $directories = glob($basePath.'/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $dirTime = filemtime($dir);
            if ($dirTime < $cutoffDate->timestamp) {
                $this->deleteDirectory($dir);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function deleteDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * @return array<int,string>
     */
    private function extractTarWarnings(string $rawOutput): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawOutput) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '' && Str::contains($line, ['Cannot open', 'Cannot stat', 'Permission denied']))
            ->values()
            ->all();
    }

    private function isIgnorableTarFailure(string $errorOutput, string $archivePath): bool
    {
        if (! file_exists($archivePath) || filesize($archivePath) === 0) {
            return false;
        }

        $lines = collect(preg_split('/\r\n|\r|\n/', $errorOutput) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return false;
        }

        return $lines->every(function (string $line): bool {
            return Str::contains($line, [
                'Cannot open: Permission denied',
                'Cannot stat: Permission denied',
                'Exiting with failure status due to previous errors',
                'file changed as we read it',
            ]);
        });
    }
}
