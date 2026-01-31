<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConsolidatedReport;
use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Repositories\SettingsRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConsolidatedReportService
{
    public function __construct(
        private readonly ActiveSubstanceService $activeSubstanceService,
        private readonly IkuService $ikuService,
        private readonly SettingsRepository $settings
    ) {}

    /**
     * Get consolidated report data for preview.
     */
    public function getPreviewData(string $periodType, Carbon $start, Carbon $end): array
    {
        return [
            'period_label' => $this->getPeriodLabel($periodType, $start, $end),
            'statistics' => $this->getStatisticsForPeriod($start, $end),
            'active_substances' => $this->getActiveSubstancesForPeriod($start, $end),
            'iku' => $periodType === 'quarterly' ? $this->ikuService->computeForPeriod($start, $end) : null,
            'comparison' => $this->getComparisonData($periodType, $start),
            'narratives' => $this->getDefaultNarratives($periodType),
            'signers' => $this->getDefaultSigners(),
        ];
    }

    /**
     * Generate and save report.
     */
    public function generate(array $data, ?int $userId = null): ConsolidatedReport
    {
        $start = Carbon::parse($data['period_start']);
        $end = Carbon::parse($data['period_end']);
        $periodType = $data['period_type'];

        // Prepare data structure
        $reportData = [
            'statistics' => $this->getStatisticsForPeriod($start, $end),
            'active_substances' => $this->getActiveSubstancesForPeriod($start, $end),
            'iku' => $periodType === 'quarterly' ? $this->ikuService->computeForPeriod($start, $end) : null,
        ];

        $comparisonData = $this->getComparisonData($periodType, $start);

        // Create database record
        $report = ConsolidatedReport::create([
            'period_type' => $periodType,
            'period_start' => $start,
            'period_end' => $end,
            'period_label' => $this->getPeriodLabel($periodType, $start, $end),
            'report_data' => $reportData,
            'comparison_data' => $comparisonData,
            'narrative_sections' => $data['narratives'],
            'signers' => $data['signers'],
            'generated_by' => $userId,
            'generated_at' => now(),
            'is_auto_generated' => false,
        ]);

        // Generate PDF
        $this->generatePdf($report);

        return $report;
    }

    /**
     * Get statistics for a specific period.
     */
    public function getStatisticsForPeriod(Carbon $start, Carbon $end): array
    {
        $requestsReceived = TestRequest::whereBetween('created_at', [$start, $end])->count();

        // FIX: Use robust logic from IkuService to handle null completed_at
        $requestsCompleted = TestRequest::whereIn('status', ['completed', 'ready_for_delivery', 'delivered'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('completed_at')
                            ->whereBetween('updated_at', [$start, $end])
                            ->whereIn('status', ['completed', 'ready_for_delivery', 'delivered']);
                    });
            })
            ->count();

        $samplesReceived = Sample::whereBetween('created_at', [$start, $end])->count();

        // FIX: Use robust logic from IkuService to handle null testing_completed_at & legacy status
        $samplesTested = Sample::whereIn('sample_status', [
            'ready_for_delivery',
            'interpretation_done',
            'tested',      // Legacy status
            'completed',   // Legacy status
        ])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('testing_completed_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('testing_completed_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->count();

        $lhuIssued = Document::whereIn('document_type', ['laporan_hasil_uji', 'lhu'])
            ->where('source', 'generated')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Calculate average processing time (days)
        // FIX: Cast to float to avoid round() errors
        $avgProcessingTime = (float) (TestRequest::whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->get()
            ->avg(fn ($req) => $req->created_at->diffInDays($req->completed_at)) ?? 0);

        // Calculate average satisfaction rating
        // FIX: Cast to float to avoid round() errors
        $avgSatisfaction = (float) (CustomerSurvey::whereBetween('submitted_at', [$start, $end])
            ->avg('score_avg') ?? 0);

        return [
            'total_requests_received' => $requestsReceived,
            'total_requests_completed' => $requestsCompleted,
            'total_samples_received' => $samplesReceived,
            'total_samples_tested' => $samplesTested,
            'total_lhu_issued' => $lhuIssued,
            'avg_processing_days' => round($avgProcessingTime, 1),
            'satisfaction_rating' => round($avgSatisfaction, 1),
        ];
    }

    /**
     * Get active substance breakdown for a specific period.
     */
    public function getActiveSubstancesForPeriod(Carbon $start, Carbon $end): array
    {
        // Reuse ActiveSubstanceService logic but filter by date
        // Note: ActiveSubstanceService::breakdown() usually takes a limit, not a date range.
        // We might need to implement a date-filtered version here or extend the service.
        // For now, let's implement a direct query for accuracy in this specific period.

        $substances = Sample::whereBetween('created_at', [$start, $end])
            ->whereNotNull('active_substance')
            ->select('active_substance', DB::raw('count(*) as total'))
            ->groupBy('active_substance')
            ->orderByDesc('total')
            ->get();

        $totalSamples = $substances->sum('total');
        $items = [];

        foreach ($substances as $item) {
            $items[] = [
                'name' => $item->active_substance,
                'count' => $item->total,
                'percentage' => $totalSamples > 0 ? round(($item->total / $totalSamples) * 100, 1) : 0,
            ];
        }

        return [
            'items' => $items,
            'total' => $totalSamples,
        ];
    }

    /**
     * Get comparison data with previous period.
     */
    public function getComparisonData(string $periodType, Carbon $currentStart): array
    {
        // Determine previous period range
        $prevStart = $currentStart->copy();
        $prevEnd = $currentStart->copy();

        switch ($periodType) {
            case 'biweekly':
                if ($currentStart->day <= 15) {
                    // Previous is 16-end of last month
                    $prevStart = $prevStart->subMonth()->startOfMonth()->addDays(15);
                    $prevEnd = $prevEnd->subMonth()->endOfMonth();
                } else {
                    // Previous is 1-15 of current month
                    $prevStart = $prevStart->startOfMonth();
                    $prevEnd = $prevEnd->startOfMonth()->addDays(14)->endOfDay();
                }
                break;
            case 'monthly':
                $prevStart->subMonth()->startOfMonth();
                $prevEnd->subMonth()->endOfMonth();
                break;
            case 'quarterly':
                $prevStart->subQuarter()->startOfQuarter();
                $prevEnd->subQuarter()->endOfQuarter();
                break;
        }

        $currentStats = $this->getStatisticsForPeriod($currentStart, $this->getPeriodEnd($periodType, $currentStart));
        $prevStats = $this->getStatisticsForPeriod($prevStart, $prevEnd);

        $changes = [];
        foreach ($currentStats as $key => $val) {
            $prevVal = $prevStats[$key] ?? 0;
            $diff = $val - $prevVal;

            // Avoid division by zero
            $diffPercent = $prevVal > 0 ? round(($diff / $prevVal) * 100, 1) : 0;
            if ($prevVal == 0 && $val > 0) {
                $diffPercent = 100;
            }

            $changes[$key] = [
                'previous' => $prevVal,
                'current' => $val,
                'diff' => $diff,
                'diff_percent' => $diffPercent,
            ];
        }

        return [
            'previous_period' => [
                'start' => $prevStart->toDateString(),
                'end' => $prevEnd->toDateString(),
                'label' => $this->getPeriodLabel($periodType, $prevStart, $prevEnd),
            ],
            'changes' => $changes,
        ];
    }

    /**
     * Get default narratives from settings.
     */
    public function getDefaultNarratives(string $periodType): array
    {
        return [
            'opening' => $this->settings->get('consolidated_report.default_narratives.opening', ''),
            'closing' => $this->settings->get('consolidated_report.default_narratives.closing', ''),
        ];
    }

    /**
     * Get default signers from settings.
     */
    public function getDefaultSigners(): array
    {
        return $this->settings->get('consolidated_report.default_signers', []);
    }

    /**
     * Generate PDF file and update record.
     */
    public function generatePdf(ConsolidatedReport $report): void
    {
        $pdf = Pdf::loadView('pdf.consolidated-report', ['report' => $report])
            ->setPaper('a4', 'portrait');

        $filename = "laporan-{$report->period_type}-{$report->period_start->format('Ymd')}.pdf";
        $path = "reports/consolidated/{$filename}";

        Storage::put($path, $pdf->output());

        $report->update([
            'pdf_path' => $path,
            'pdf_size' => Storage::size($path),
        ]);
    }

    private function getPeriodLabel(string $type, Carbon $start, Carbon $end): string
    {
        Carbon::setLocale('id');

        return match ($type) {
            'biweekly' => 'Bi-weekly '.$start->format('d').'-'.$end->translatedFormat('d F Y'),
            'monthly' => 'Bulan '.$start->translatedFormat('F Y'),
            'quarterly' => 'Triwulan '.$start->quarter.' Tahun '.$start->year,
            default => $start->format('d/m/Y').' - '.$end->format('d/m/Y'),
        };
    }

    private function getPeriodEnd(string $type, Carbon $start): Carbon
    {
        $end = $start->copy();

        return match ($type) {
            'biweekly' => $start->day <= 15 ? $end->startOfMonth()->addDays(14)->endOfDay() : $end->endOfMonth(),
            'monthly' => $end->endOfMonth(),
            'quarterly' => $end->endOfQuarter(),
            default => $end,
        };
    }

    /**
     * Determine if reports should be auto-generated today.
     */
    public function shouldAutoGenerate(): array
    {
        $today = Carbon::now('Asia/Jakarta');
        $reports = [];

        // Bi-weekly: Generate on 16th (for 1-15) and 1st (for 16-end)
        if ($today->day === 16) {
            $reports[] = [
                'type' => 'biweekly',
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy()->startOfMonth()->addDays(14)->endOfDay(),
            ];
        }

        if ($today->day === 1) {
            // Bi-weekly for 16-end of previous month
            $reports[] = [
                'type' => 'biweekly',
                'start' => $today->copy()->subMonth()->startOfMonth()->addDays(15),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ];

            // Monthly for previous month
            $reports[] = [
                'type' => 'monthly',
                'start' => $today->copy()->subMonth()->startOfMonth(),
                'end' => $today->copy()->subMonth()->endOfMonth(),
            ];

            // Quarterly on Jan 1, Apr 1, Jul 1, Oct 1
            if (in_array($today->month, [1, 4, 7, 10])) {
                $quarterStart = $today->copy()->subQuarter()->startOfQuarter();
                $reports[] = [
                    'type' => 'quarterly',
                    'start' => $quarterStart,
                    'end' => $quarterStart->copy()->endOfQuarter(),
                ];
            }
        }

        return $reports;
    }
}
