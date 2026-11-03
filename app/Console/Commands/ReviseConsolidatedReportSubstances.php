<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ConsolidatedReport;
use App\Services\ConsolidatedReportService;
use Illuminate\Console\Command;

class ReviseConsolidatedReportSubstances extends Command
{
    protected $signature = 'reports:revise-active-substances {report : ID laporan asli} {--reason= : Alasan revisi} {--apply : Simpan revisi dan PDF baru tanpa notifikasi}';

    protected $description = 'Pratinjau atau simpan revisi rekap zat aktif tanpa mengubah laporan asli';

    public function handle(ConsolidatedReportService $service): int
    {
        $report = ConsolidatedReport::findOrFail($this->argument('report'));
        $data = $service->getActiveSubstancesForPeriod($report->period_start, $report->period_end);
        $this->table(['Zat aktif', 'Jumlah temuan'], array_map(fn ($item) => [$item['name'], $item['count']], $data['items']));
        $this->line("Sampel teruji: {$data['sample_total']}; hasil belum lengkap: {$data['missing_result_samples']}.");
        if (! $data['is_complete'] || $data['sample_total'] !== (int) ($report->report_data['statistics']['total_samples_tested'] ?? -1)) {
            $this->error($data['message'] ?: 'Jumlah sampel saat ini berbeda dari laporan asli. Revisi dibatalkan.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Pratinjau saja. Tidak ada data, PDF, atau notifikasi yang ditulis.');

            return self::SUCCESS;
        }
        $revision = $service->reviseActiveSubstances($report, (string) $this->option('reason'));
        $this->info("Revisi tersimpan: ID {$revision->id}, revisi {$revision->revision_number}. Laporan asli dipertahankan; tidak ada notifikasi dikirim.");

        return self::SUCCESS;
    }
}
