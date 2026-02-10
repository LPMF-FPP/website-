<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Sample;
use App\Models\SampleTestProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMissingPreparationCommand extends Command
{
    protected $signature = 'app:backfill-missing-preparation {--dry-run : Show what would be done without making changes}';

    protected $description = 'Backfill missing preparation records for samples that are ready_for_delivery but missing the preparation stage';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN — tidak ada perubahan yang akan dibuat.');
        }

        $samples = Sample::where('status', 'ready_for_delivery')
            ->whereDoesntHave('testProcesses', function ($q) {
                $q->where('stage', 'preparation');
            })
            ->whereHas('testProcesses', function ($q) {
                $q->where('stage', 'instrumentation')->whereNotNull('completed_at');
            })
            ->whereHas('testProcesses', function ($q) {
                $q->where('stage', 'interpretation')->whereNotNull('completed_at');
            })
            ->with(['testProcesses', 'testRequest:id,request_number'])
            ->get();

        if ($samples->isEmpty()) {
            $this->info('Tidak ada sampel dengan preparation record yang hilang.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$samples->count()} sampel dengan preparation record hilang:");
        $this->newLine();

        $headers = ['Sample Code', 'Sample ID', 'Request', 'Existing Stages', 'Backfill Started At'];
        $rows = [];

        foreach ($samples as $sample) {
            $existingStages = $sample->testProcesses
                ->map(fn ($p) => is_object($p->stage) ? $p->stage->value : $p->stage)
                ->implode(', ');

            $earliestProcess = $sample->testProcesses
                ->sortBy(fn ($p) => $p->started_at ?? $p->created_at)
                ->first();

            $backfillStartedAt = $earliestProcess?->started_at ?? $earliestProcess?->created_at ?? now();

            $rows[] = [
                $sample->sample_code,
                $sample->id,
                $sample->testRequest->request_number ?? "ID:{$sample->test_request_id}",
                $existingStages,
                $backfillStartedAt->toDateTimeString(),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($isDryRun) {
            $this->warn('DRY RUN selesai. Jalankan tanpa --dry-run untuk menerapkan perubahan.');

            return self::SUCCESS;
        }

        $created = 0;
        $errors = 0;

        foreach ($samples as $sample) {
            $earliestProcess = $sample->testProcesses
                ->sortBy(fn ($p) => $p->started_at ?? $p->created_at)
                ->first();

            $backfillStartedAt = $earliestProcess?->started_at ?? $earliestProcess?->created_at ?? now();

            try {
                DB::transaction(function () use ($sample, $earliestProcess, $backfillStartedAt) {
                    SampleTestProcess::create([
                        'sample_id' => $sample->id,
                        'stage' => 'preparation',
                        'performed_by' => $earliestProcess?->performed_by,
                        'started_at' => $backfillStartedAt,
                        'completed_at' => $backfillStartedAt,
                        'notes' => 'Backfill: preparation record dibuat otomatis karena data hilang.',
                        'metadata' => [
                            'backfilled' => true,
                            'backfilled_at' => now()->toISOString(),
                            'reason' => 'Missing preparation stage record',
                        ],
                    ]);
                });

                $created++;
                $this->info("  {$sample->sample_code} (ID: {$sample->id}) — preparation record dibuat.");
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $this->warn("  {$sample->sample_code} (ID: {$sample->id}) — sudah ada, dilewati.");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  {$sample->sample_code} (ID: {$sample->id}) — ERROR: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Selesai. Dibuat: {$created}, Error: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
