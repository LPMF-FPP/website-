<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ConsolidatedReport;
use App\Services\ConsolidatedReportService;
use Illuminate\Console\Command;

class ReviseConsolidatedReportSummary extends Command
{
    protected $signature = 'reports:revise-summary {report : ID laporan asli atau revisinya} {--reason= : Alasan revisi} {--apply : Simpan revisi tanpa notifikasi}';

    protected $description = 'Pratinjau atau revisi ringkasan operasional dan target tanpa menimpa laporan lama';

    public function handle(ConsolidatedReportService $service): int
    {
        $report = ConsolidatedReport::findOrFail($this->argument('report'));
        $summary = $service->getOperationalSummaryForPeriod($report->period_start, $report->period_end);
        $this->table(['Indikator', 'Periode laporan', 'Sejak 1 Januari', 'Target', 'Capaian'],
            array_map(fn ($row) => [$row['category'], $row['period_value'], $row['year_value'], $row['target_description'] ?? '-', $row['status'] ?? '-'], $summary['rows']));
        if (! $this->option('apply')) {
            $this->info('Pratinjau saja. Tidak ada data, PDF, atau notifikasi yang ditulis.');

            return self::SUCCESS;
        }
        $revision = $service->reviseOperationalSummary($report, (string) $this->option('reason'));
        $this->info("Revisi ringkasan tersimpan: ID {$revision->id}, revisi {$revision->revision_number}. Tidak ada notifikasi dikirim.");

        return self::SUCCESS;
    }
}
