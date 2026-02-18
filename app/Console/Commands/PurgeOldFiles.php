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
        $basePath = rtrim(settings('retention.base_path', 'official_docs/'), '/').'/';
        $disk = settings('retention.storage_driver', 'public');
        $threshold = CarbonImmutable::now()->subDays($days);

        $storage = Storage::disk($disk);

        if (! $storage->exists($basePath)) {
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

        $previewCleanup = $this->purgeFrV2PreviewArtifacts();
        if ($previewCleanup['checked'] > 0) {
            $this->info(sprintf(
                'FR-v2 preview cleanup: checked=%d, cleaned=%d, failed=%d.',
                $previewCleanup['checked'],
                $previewCleanup['cleaned'],
                $previewCleanup['failed']
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{checked:int, cleaned:int, failed:int}
     */
    private function purgeFrV2PreviewArtifacts(): array
    {
        if (! (bool) config('quality.fr_v2.enabled', false)) {
            return ['checked' => 0, 'cleaned' => 0, 'failed' => 0];
        }

        $disk = (string) config('quality.fr_v2.source_pdf_disk', 'local');
        $basePath = trim((string) config('quality.fr_v2.preview_temp_dir', 'qmh/fr-v2/preview-temp'), '/');
        $ttlMinutes = max(1, (int) config('quality.fr_v2.preview_temp_ttl_minutes', 120));
        $threshold = CarbonImmutable::now()->subMinutes($ttlMinutes);

        $storage = Storage::disk($disk);
        if ($basePath === '' || ! $storage->exists($basePath)) {
            return ['checked' => 0, 'cleaned' => 0, 'failed' => 0];
        }

        $checked = 0;
        $cleaned = 0;
        $failed = 0;

        foreach ($storage->allFiles($basePath) as $file) {
            $checked++;
            $modified = CarbonImmutable::createFromTimestamp($storage->lastModified($file));
            if ($modified->greaterThan($threshold)) {
                continue;
            }

            try {
                $deleted = $storage->delete($file);
            } catch (\Throwable) {
                $deleted = false;
            }

            if (! $deleted) {
                $failed++;

                continue;
            }

            $cleaned++;

            Audit::log('PURGE_QMH_FR_V2_PREVIEW_FILE', $file, null, null, ['deleted_at' => now()->toISOString()]);
        }

        return [
            'checked' => $checked,
            'cleaned' => $cleaned,
            'failed' => $failed,
        ];
    }
}
