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
    public const DEFAULT_SIGNERS_STRUCTURE = [
        ['role' => 'Pembuat', 'name' => '', 'position' => '', 'nip' => ''],
        ['role' => 'Pemeriksa', 'name' => '', 'position' => '', 'nip' => ''],
        ['role' => 'Pengesah', 'name' => '', 'position' => '', 'nip' => ''],
    ];

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
            'processing_time' => $this->getProcessingTimeBreakdown($start, $end),
            'satisfaction' => $this->getSatisfactionBreakdown($start, $end),
            'gender' => $this->getGenderBreakdown($start, $end),
            'jurisdiction' => $this->getJurisdictionBreakdown($start, $end),
            'age_range' => $this->getAgeRangeBreakdown($start, $end),
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
            'processing_time' => $this->getProcessingTimeBreakdown($start, $end),
            'satisfaction' => $this->getSatisfactionBreakdown($start, $end),
            'gender' => $this->getGenderBreakdown($start, $end),
            'jurisdiction' => $this->getJurisdictionBreakdown($start, $end),
            'age_range' => $this->getAgeRangeBreakdown($start, $end),
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
        // FIX: Match logic with DashboardController (submitted_at -> completed_at/updated_at, weekdays only)
        $processingTimes = TestRequest::whereIn('status', ['completed', 'ready_for_delivery', 'delivered'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('completed_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->get()
            ->map(function ($req) {
                $start = $req->submitted_at ?? $req->created_at;
                $end = $req->completed_at ?? $req->updated_at;

                if (! $start || ! $end || $end->lt($start)) {
                    return 0;
                }

                return $start->diffInWeekdays($end);
            });

        $avgProcessingTime = (float) ($processingTimes->avg() ?? 0);

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
        // Use case-insensitive grouping to merge "tramadol" and "Tramadol" into one entry
        // LOWER() ensures consistent grouping regardless of input case
        $substances = Sample::whereBetween('created_at', [$start, $end])
            ->whereNotNull('active_substance')
            ->select(
                DB::raw('LOWER(TRIM(active_substance)) as normalized_substance'),
                DB::raw('count(*) as total')
            )
            ->groupBy(DB::raw('LOWER(TRIM(active_substance))'))
            ->orderByDesc('total')
            ->get();

        $totalSamples = $substances->sum('total');
        $items = [];

        foreach ($substances as $item) {
            // Normalize display name: "tramadol" -> "Tramadol"
            $displayName = ucwords(strtolower(trim($item->normalized_substance)));

            $items[] = [
                'name' => $displayName,
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
     * Get processing time breakdown for a period.
     */
    public function getProcessingTimeBreakdown(Carbon $start, Carbon $end): array
    {
        $requests = TestRequest::whereIn('status', ['completed', 'ready_for_delivery', 'delivered'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('completed_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->get()
            ->map(function ($req) {
                $start = $req->submitted_at ?? $req->created_at;
                $end = $req->completed_at ?? $req->updated_at;

                if (! $start || ! $end || $end->lt($start)) {
                    return 0;
                }

                return $start->diffInWeekdays($end);
            });

        $total = $requests->count();

        $categories = [
            '≤ 3 hari' => $requests->filter(fn ($d) => $d <= 3)->count(),
            '4-7 hari' => $requests->filter(fn ($d) => $d >= 4 && $d <= 7)->count(),
            '8-14 hari' => $requests->filter(fn ($d) => $d >= 8 && $d <= 14)->count(),
            '> 14 hari' => $requests->filter(fn ($d) => $d > 14)->count(),
        ];

        return [
            'categories' => collect($categories)->map(fn ($count, $label) => [
                'label' => $label,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ])->values()->toArray(),
            'total' => $total,
            'avg_days' => $total > 0 ? round($requests->avg(), 1) : 0,
        ];
    }

    /**
     * Get customer satisfaction breakdown for a period.
     */
    public function getSatisfactionBreakdown(Carbon $start, Carbon $end): array
    {
        $surveys = CustomerSurvey::whereBetween('submitted_at', [$start, $end])
            ->whereNotNull('score_avg')
            ->get();

        $total = $surveys->count();
        $avgScore = (float) ($surveys->avg('score_avg') ?? 0);

        // Group by rounded score (1-4)
        $ratings = [
            4 => ['label' => 'Sangat Puas (4)', 'count' => 0],
            3 => ['label' => 'Puas (3)', 'count' => 0],
            2 => ['label' => 'Kurang Puas (2)', 'count' => 0],
            1 => ['label' => 'Tidak Puas (1)', 'count' => 0],
        ];

        foreach ($surveys as $survey) {
            $rounded = (int) round((float) $survey->score_avg);
            $rounded = max(1, min(4, $rounded)); // Clamp 1-4
            $ratings[$rounded]['count']++;
        }

        return [
            'ratings' => collect($ratings)->map(fn ($r) => [
                'label' => $r['label'],
                'count' => $r['count'],
                'percentage' => $total > 0 ? round(($r['count'] / $total) * 100, 1) : 0,
            ])->values()->toArray(),
            'total_respondents' => $total,
            'avg_score' => round($avgScore, 2),
        ];
    }

    /**
     * Get suspect gender breakdown for a period.
     */
    public function getGenderBreakdown(Carbon $start, Carbon $end): array
    {
        $requests = TestRequest::whereBetween('created_at', [$start, $end])
            ->select('suspect_gender', DB::raw('count(*) as total'))
            ->groupBy('suspect_gender')
            ->get();

        // Normalize gender variations (male/female, Laki-laki/Perempuan, L/P, etc)
        $maleCount = 0;
        $femaleCount = 0;
        $unknownCount = 0;

        foreach ($requests as $req) {
            $gender = strtolower(trim($req->suspect_gender ?? ''));

            if (in_array($gender, ['l', 'laki-laki', 'laki', 'pria', 'male', 'm'])) {
                $maleCount += $req->total;
            } elseif (in_array($gender, ['p', 'perempuan', 'wanita', 'female', 'f'])) {
                $femaleCount += $req->total;
            } else {
                $unknownCount += $req->total;
            }
        }

        $total = $maleCount + $femaleCount + $unknownCount;

        $genders = [
            ['label' => 'Laki-laki', 'count' => $maleCount],
            ['label' => 'Perempuan', 'count' => $femaleCount],
            ['label' => 'Tidak Diketahui', 'count' => $unknownCount],
        ];

        return [
            'items' => collect($genders)->map(fn ($g) => [
                'label' => $g['label'],
                'count' => $g['count'],
                'percentage' => $total > 0 ? round(($g['count'] / $total) * 100, 1) : 0,
            ])->toArray(),
            'total' => $total,
        ];
    }

    /**
     * Get jurisdiction (asal user) breakdown for a period.
     */
    public function getJurisdictionBreakdown(Carbon $start, Carbon $end): array
    {
        $data = TestRequest::whereBetween('test_requests.created_at', [$start, $end])
            ->join('investigators', 'test_requests.investigator_id', '=', 'investigators.id')
            ->select('investigators.jurisdiction', DB::raw('count(*) as total'))
            ->groupBy('investigators.jurisdiction')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $total = TestRequest::whereBetween('created_at', [$start, $end])->count();

        return [
            'items' => $data->map(fn ($item) => [
                'label' => $item->jurisdiction ?: 'Tidak Diketahui',
                'count' => $item->total,
                'percentage' => $total > 0 ? round(($item->total / $total) * 100, 1) : 0,
            ])->toArray(),
            'total' => $total,
        ];
    }

    /**
     * Get suspect age range breakdown for a period.
     */
    public function getAgeRangeBreakdown(Carbon $start, Carbon $end): array
    {
        $requests = TestRequest::whereBetween('created_at', [$start, $end])
            ->whereNotNull('suspect_age')
            ->pluck('suspect_age');

        $nullCount = TestRequest::whereBetween('created_at', [$start, $end])
            ->whereNull('suspect_age')
            ->count();

        $total = $requests->count() + $nullCount;

        $ranges = [
            '< 18 tahun' => $requests->filter(fn ($a) => $a < 18)->count(),
            '18-25 tahun' => $requests->filter(fn ($a) => $a >= 18 && $a <= 25)->count(),
            '26-35 tahun' => $requests->filter(fn ($a) => $a >= 26 && $a <= 35)->count(),
            '36-45 tahun' => $requests->filter(fn ($a) => $a >= 36 && $a <= 45)->count(),
            '46-55 tahun' => $requests->filter(fn ($a) => $a >= 46 && $a <= 55)->count(),
            '> 55 tahun' => $requests->filter(fn ($a) => $a > 55)->count(),
            'Tidak Diketahui' => $nullCount,
        ];

        return [
            'items' => collect($ranges)->map(fn ($count, $label) => [
                'label' => $label,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ])->values()->toArray(),
            'total' => $total,
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
        $signers = $this->settings->get('consolidated_report.default_signers', null);

        if (empty($signers)) {
            return self::DEFAULT_SIGNERS_STRUCTURE;
        }

        return $signers;
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
