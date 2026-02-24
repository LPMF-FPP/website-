<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCleanupCommand extends Command
{
    protected $signature = 'backup:cleanup {--days=}';

    protected $description = 'Clean up old emergency backups based on retention policy';

    public function handle(BackupService $service): int
    {
        $optionDays = $this->option('days');
        $days = $optionDays !== null && $optionDays !== ''
            ? (int) $optionDays
            : (int) settings('backup.retention_days', 14);
        $days = max(1, min(3650, $days));

        $this->info("Cleaning up backups older than {$days} days...");

        $deleted = $service->cleanupOldBackups($days, 'emergency');

        $cutoffDate = now()->subDays($days);
        BackupRun::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$deleted} old backup directory(ies)");
        $this->info('Database records cleaned up');

        return self::SUCCESS;
    }
}
