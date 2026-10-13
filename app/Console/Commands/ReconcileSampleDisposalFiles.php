<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SampleDisposalFileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileSampleDisposalFiles extends Command
{
    protected $signature = 'sample-disposal:reconcile-files {--apply : Retry pending file operations}';

    protected $description = 'Inspect or retry unresolved sample-disposal evidence file operations';

    public function handle(SampleDisposalFileService $files): int
    {
        $pending = DB::table('sample_disposal_file_operations')->whereIn('state', ['pending', 'copied'])->count();
        $this->info('Operasi file tertunda: '.$pending);
        if (! $this->option('apply')) {
            $this->info('Dry-run; tidak ada file yang diubah.');

            return self::SUCCESS;
        }
        $result = $files->retry();
        $this->info('Selesai: '.$result['completed'].'; masih gagal: '.$result['failed']);

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
