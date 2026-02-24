<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Models\JobStatus;
use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EmergencyBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public readonly int $backupRunId,
        public readonly string $jobStatusId
    ) {}

    public function handle(BackupService $service): void
    {
        $backupRun = BackupRun::findOrFail($this->backupRunId);
        $jobStatus = JobStatus::findOrFail($this->jobStatusId);

        try {
            $backupRun->markAsRunning();
            $jobStatus->markAsRunning();
            $jobStatus->updateProgress(1, 5);

            $outputDir = $service->createBackupDirectory('emergency');
            $backupRun->update(['artifact_dir' => $outputDir]);

            $jobStatus->updateProgress(2, 5);
            $dbDump = $service->createDatabaseDump($outputDir);
            $backupRun->update([
                'db_dump_path' => $dbDump['path'],
                'db_size_bytes' => $dbDump['size'],
            ]);

            $jobStatus->updateProgress(3, 5);
            $storageArchive = $service->createStorageArchive($outputDir);
            $backupRun->update([
                'storage_archive_path' => $storageArchive['path'],
                'storage_size_bytes' => $storageArchive['size'],
            ]);

            $storageWarnings = $storageArchive['warnings'] ?? [];
            if (! empty($storageWarnings)) {
                Log::warning('Emergency backup archive completed with unreadable paths', [
                    'backup_run_id' => $backupRun->id,
                    'warnings_count' => count($storageWarnings),
                    'warnings_sample' => array_slice($storageWarnings, 0, 10),
                ]);
            }

            $jobStatus->updateProgress(4, 5);
            $manifest = $service->generateManifest($outputDir, [
                'database' => $dbDump['path'],
                'storage' => $storageArchive['path'],
            ], [
                'storage_warnings' => $storageWarnings,
            ]);

            $backupRun->update([
                'manifest_path' => $manifest['path'],
                'git_commit' => $manifest['data']['git_commit'],
                'sha256_manifest' => json_encode($manifest['data']['files']),
            ]);

            $jobStatus->updateProgress(5, 5);
            $backupRun->markAsSuccess([]);
            $jobStatus->markAsCompleted([
                'backup_run_id' => $backupRun->id,
                'artifact_dir' => $outputDir,
                'total_size' => $backupRun->getTotalSizeBytes(),
                'formatted_size' => $backupRun->getFormattedSize(),
            ]);

            Log::info('Emergency backup completed', [
                'backup_run_id' => $backupRun->id,
                'size' => $backupRun->getFormattedSize(),
            ]);

        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $backupRun->markAsFailed($error);
            $jobStatus->markAsFailed($error);

            Log::error('Emergency backup failed', [
                'backup_run_id' => $backupRun->id,
                'error' => $error,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
