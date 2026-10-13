<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SampleDisposalFileService;
use Illuminate\Console\Command;
use Throwable;

class PrivatizeSampleDisposalFiles extends Command
{
    protected $signature = 'sample-disposal:privatize-files {--apply : Copy verified legacy evidence to private storage and remove public originals}';

    protected $description = 'Inspect or safely relocate legacy public sample-disposal photos';

    public function handle(SampleDisposalFileService $files): int
    {
        $paths = $files->legacyPaths();
        $this->info('Path bukti legacy tercatat: '.count($paths));
        if (! $this->option('apply')) {
            $this->info('Dry-run; metadata dan file tidak diubah.');

            return self::SUCCESS;
        }
        $failed = 0;
        foreach ($paths as $path) {
            try {
                if (! $files->privatize($path)) {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->error('Bukti tidak dapat diproses: '.get_class($exception));
            }
        }
        $this->info('Selesai: '.(count($paths) - $failed).'; perlu rekonsiliasi: '.$failed);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
