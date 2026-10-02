<?php

namespace App\Console\Commands;

use App\Models\ConsolidatedReport;
use App\Models\SystemSetting;
use App\Services\ConsolidatedReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class GenerateConsolidatedReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generate-consolidated 
                            {--type= : Period type (biweekly|monthly|quarterly)}
                            {--force : Force generate even if already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generate consolidated periodic reports';

    public function __construct(
        private readonly ConsolidatedReportService $reportService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for scheduled reports...');

        // Check if auto-generate is enabled
        $enabled = SystemSetting::where('key', 'consolidated_report.auto_generate_enabled')->value('value');
        if ($enabled === '0' || $enabled === false) {
            $this->warn('Auto-generation is disabled in settings.');

            return Command::SUCCESS;
        }

        // Determine reports to generate
        $manualType = $this->option('type');
        $force = $this->option('force');
        $reportsToGenerate = [];

        if ($manualType) {
            // Manual trigger logic (simplified for testing)
            $now = Carbon::now('Asia/Jakarta');
            // If manual type provided, we assume current period context
            if ($manualType === 'quarterly') {
                $quarterStart = $now->copy()->startOfQuarter()->subQuarter();
                $reportsToGenerate[] = [
                    'type' => 'quarterly',
                    'start' => $quarterStart,
                    'end' => $quarterStart->copy()->endOfQuarter(),
                ];
            } else {
                $reportsToGenerate[] = [
                    'type' => $manualType,
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];
            }
        } else {
            // Automatic logic based on date
            $reportsToGenerate = $this->reportService->shouldAutoGenerate();
        }

        if (empty($reportsToGenerate)) {
            $this->info('No reports scheduled for today.');

            return Command::SUCCESS;
        }

        foreach ($reportsToGenerate as $config) {
            $this->info("Generating {$config['type']} report...");

            try {
                // Get defaults
                $narratives = $this->reportService->getDefaultNarratives($config['type']);
                $signers = $this->reportService->getDefaultSigners();

                // Generate
                $report = $this->reportService->generate([
                    'period_type' => $config['type'],
                    'period_start' => $config['start'],
                    'period_end' => $config['end'],
                    'narratives' => $narratives,
                    'signers' => $signers,
                ], null, true); // System generated, auto-generated = true

                $this->info("Report generated successfully: ID {$report->id}");

                // Send Notification via Service
                $notifiedCount = $this->reportService->sendGenerationNotification($report);
                if ($notifiedCount > 0) {
                    $this->info('Notification dispatched to admin.');
                }

            } catch (ConflictHttpException $e) {
                if (! $force) {
                    $this->warn($e->getMessage());

                    continue;
                }

                $baseReport = ConsolidatedReport::query()
                    ->where('period_type', $config['type'])
                    ->whereDate('period_start', Carbon::parse($config['start'])->toDateString())
                    ->whereDate('period_end', Carbon::parse($config['end'])->toDateString())
                    ->whereNull('revision_of_id')
                    ->first();

                if (! $baseReport) {
                    $this->error('Laporan dasar untuk revisi paksa tidak ditemukan.');

                    continue;
                }

                $revision = $this->reportService->revise(
                    $baseReport,
                    'Revisi dibuat atas permintaan operator melalui opsi --force.',
                    null
                );
                $this->info("Report revision generated successfully: ID {$revision->id}");
                $notifiedCount = $this->reportService->sendGenerationNotification($revision);
                if ($notifiedCount > 0) {
                    $this->info('Notification dispatched to admin.');
                }
            } catch (\Exception $e) {
                // Ignore unique constraint violation if not force
                if (str_contains($e->getMessage(), 'unique_period') && ! $force) {
                    $this->warn('Report already exists for this period. Skipping.');

                    continue;
                }

                $this->error('Failed to generate report: '.$e->getMessage());
                Log::error('Auto-generate report failed: '.$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
