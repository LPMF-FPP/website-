<?php

namespace App\Console\Commands;

use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOldFiles extends Command
{
    protected $signature = 'lims:purge-old-files';

    protected $description = 'Purge generated documents that exceed the retention window.';

    public function handle(): int
    {
        $days = (int) settings('retention.purge_after_days', 1825);
        $basePath = rtrim(settings('retention.base_path', 'official_docs/'), '/') . '/';
        $disk = settings('retention.storage_driver', 'local');
        $threshold = CarbonImmutable::now()->subDays($days);

        $storage = Storage::disk($disk);

        if (!$storage->exists($basePath)) {
            $this->info('Base path not found, nothing to purge.');
            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($storage->allFiles($basePath) as $file) {
            $modified = CarbonImmutable::createFromTimestamp($storage->lastModified($file));
            if ($modified->lessThan($threshold)) {
                $storage->delete($file);
                $deleted++;
                Audit::log('PURGE_FILE', $file, null, null, ['deleted_at' => now()->toISOString()]);
            }
        }

        $this->info("Purged {$deleted} file(s).");

        return self::SUCCESS;
    }
}
