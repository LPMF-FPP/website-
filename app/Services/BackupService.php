<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class BackupService
{
    private const BACKUP_PROCESS_TIMEOUT_SECONDS = 1800;

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

        $result = Process::timeout(self::BACKUP_PROCESS_TIMEOUT_SECONDS)->run($command);

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

        $excludes = array_merge([
            'backups',
            'public/.gitignore',
            '.gitignore',
        ], $this->findUnreadableStoragePaths($storagePath));

        $excludeArgs = implode(' ', array_map(
            fn ($ex) => '--exclude='.escapeshellarg($ex),
            $excludes
        ));

        $command = sprintf(
            'tar -czf %s -C %s %s .',
            escapeshellarg($archivePath),
            escapeshellarg($storagePath),
            $excludeArgs
        );

        $result = Process::timeout(self::BACKUP_PROCESS_TIMEOUT_SECONDS)->run($command);

        if ($result->failed()) {
            throw new \RuntimeException('Storage archive failed: '.$result->errorOutput());
        }

        return [
            'path' => $archivePath,
            'size' => filesize($archivePath),
        ];
    }

    private function findUnreadableStoragePaths(string $storagePath): array
    {
        $unreadablePaths = [];

        $this->collectUnreadablePaths($storagePath, $storagePath, $unreadablePaths);

        return array_values(array_unique($unreadablePaths));
    }

    private function collectUnreadablePaths(string $currentPath, string $rootPath, array &$unreadablePaths): void
    {
        $entries = @scandir($currentPath);

        if ($entries === false) {
            $relativePath = ltrim(substr($currentPath, strlen($rootPath)), '/');

            if ($relativePath !== '') {
                $unreadablePaths[] = $relativePath;
            }

            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $currentPath.'/'.$entry;
            $relativePath = ltrim(substr($path, strlen($rootPath)), '/');

            if (! is_readable($path)) {
                $unreadablePaths[] = $relativePath;

                continue;
            }

            if (is_dir($path)) {
                $this->collectUnreadablePaths($path, $rootPath, $unreadablePaths);
            }
        }
    }

    public function generateManifest(string $outputPath, array $files): array
    {
        $manifest = [
            'created_at' => now()->toIso8601String(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'git_commit' => $this->getGitCommit(),
            'files' => [],
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
}
