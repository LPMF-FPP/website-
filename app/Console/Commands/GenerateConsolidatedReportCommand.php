<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppMessageBatch;
use App\Services\ConsolidatedReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            $reportsToGenerate[] = [
                'type' => $manualType,
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ];
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
                ], null); // System generated (user_id = null)

                // Mark as auto-generated
                $report->update(['is_auto_generated' => true]);

                $this->info("Report generated successfully: ID {$report->id}");

                // Send Notification
                $this->sendNotification($report);

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

    private function sendNotification($report): void
    {
        // Check if notification enabled
        $notifyEnabled = SystemSetting::where('key', 'consolidated_report.notify_on_generate')->value('value');
        if ($notifyEnabled === '0' || $notifyEnabled === false) {
            return;
        }

        // Get admins
        $admins = User::role('admin')->whereNotNull('phone')->get();
        if ($admins->isEmpty()) {
            return;
        }

        $appUrl = config('app.url');
        $message = "📊 *LAPORAN GABUNGAN PERIODIK*\n\n"
            ."Laporan {$report->period_type} periode {$report->period_label} telah di-generate secara otomatis.\n\n"
            ."📅 Periode: {$report->period_start->format('d/m/Y')} - {$report->period_end->format('d/m/Y')}\n"
            ."⏰ Waktu Generate: {$report->generated_at->format('d/m/Y H:i')}\n"
            .'📈 Total Permintaan: '.($report->report_data['statistics']['total_requests_received'] ?? 0)."\n"
            .'🧪 Total Sampel: '.($report->report_data['statistics']['total_samples_received'] ?? 0)."\n\n"
            ."Silakan akses laporan di:\n"
            ."{$appUrl}/statistics?tab=reports\n\n"
            ."—\n"
            .'Staff Laboratorium Farmapol Pusdokkes Polri';

        // Create batch log
        $batch = WhatsAppMessageBatch::create([
            'type' => 'consolidated_report',
            'reference_id' => $report->id,
            'total_messages' => $admins->count(),
            'status' => 'processing',
        ]);

        foreach ($admins as $admin) {
            SendWhatsAppMessage::dispatch($admin->phone, $message, $batch->id);
        }

        $batch->update(['status' => 'completed']);
        $this->info("Notification dispatched to {$admins->count()} admins.");
    }
}
