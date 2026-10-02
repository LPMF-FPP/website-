<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ConsolidatedReport;
use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Services\IkuService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReconcileIkuPeriod extends Command
{
    protected $signature = 'iku:reconcile
        {--start= : Start date in YYYY-MM-DD}
        {--end= : End date in YYYY-MM-DD}
        {--report-id= : Existing consolidated report to compare}';

    protected $description = 'Read-only comparison of saved IKU counts and current source counts';

    public function __construct(private readonly IkuService $ikuService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $start = Carbon::parse((string) $this->option('start'))->startOfDay();
            $end = Carbon::parse((string) $this->option('end'))->endOfDay();
        } catch (\Throwable) {
            $this->error('Berikan tanggal valid dengan format YYYY-MM-DD.');

            return self::INVALID;
        }

        if (! $this->option('start') || ! $this->option('end') || $end->lt($start)) {
            $this->error('Opsi --start dan --end wajib diisi dengan rentang yang valid.');

            return self::INVALID;
        }

        if (
            $start->toDateString() !== $start->copy()->startOfQuarter()->toDateString()
            || $end->toDateString() !== $start->copy()->endOfQuarter()->toDateString()
            || $start->year !== $end->year
        ) {
            $this->error('Rekonsiliasi IKU hanya menerima satu triwulan kalender penuh.');

            return self::INVALID;
        }

        $report = $this->option('report-id')
            ? ConsolidatedReport::query()->find($this->option('report-id'))
            : null;

        if ($this->option('report-id') && ! $report) {
            $this->error('Laporan tidak ditemukan.');

            return self::FAILURE;
        }

        if ($report && (
            $report->period_type !== 'quarterly'
            || $report->period_start->toDateString() !== $start->toDateString()
            || $report->period_end->toDateString() !== $end->toDateString()
        )) {
            $this->error('Rentang harus sama persis dengan periode triwulan pada laporan.');

            return self::INVALID;
        }

        $current = $this->ikuService->computeForPeriod($start, $end, 'quarterly');
        $freshStatistics = [
            'requests_submitted' => $this->ikuService->countRequestsSubmittedForPeriod($start, $end),
            'requests_handed_over' => $this->ikuService->countRequestsHandedOverForPeriod($start, $end),
            'samples_tested' => $this->ikuService->countSamplesCompletedForPeriod($start, $end),
            'ready_samples_proxy' => Sample::whereHas('testRequest', function ($query) use ($start, $end): void {
                $query->whereNotNull('ready_for_delivery_at')
                    ->whereBetween('ready_for_delivery_at', [$start, $end]);
            })->count(),
            'lhu_documents' => Document::whereIn('document_type', ['laporan_hasil_uji', 'lhu'])
                ->where('source', 'generated')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'surveys_submitted' => CustomerSurvey::whereBetween('submitted_at', [$start, $end])->count(),
        ];

        $timestampBreakdown = TestRequest::query()
            ->whereIn('status', ['completed', 'ready_for_delivery', 'delivered'])
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($fallback) use ($start, $end): void {
                        $fallback->whereNull('completed_at')
                            ->whereBetween('ready_for_delivery_at', [$start, $end]);
                    })
                    ->orWhere(function ($fallback) use ($start, $end): void {
                        $fallback->whereNull('completed_at')
                            ->whereNull('ready_for_delivery_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->selectRaw(
                "status, CASE WHEN completed_at BETWEEN ? AND ? THEN 'completed_at' "
                ."WHEN ready_for_delivery_at BETWEEN ? AND ? THEN 'ready_for_delivery_at' "
                ."ELSE 'updated_at_fallback' END AS timestamp_source, COUNT(*) AS total",
                [$start, $end, $start, $end]
            )
            ->groupBy('status', 'timestamp_source')
            ->orderBy('status')
            ->orderBy('timestamp_source')
            ->get();

        $submissionTimestampBreakdown = [
            'submitted_at' => TestRequest::whereBetween('submitted_at', [$start, $end])->count(),
            'created_at_fallback' => TestRequest::whereNull('submitted_at')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
        ];

        $sampleTimestampBreakdown = [
            'testing_completed_at' => Sample::whereIn('sample_status', [
                'ready_for_delivery', 'interpretation_done', 'tested', 'completed',
            ])->whereBetween('testing_completed_at', [$start, $end])->count(),
            'updated_at_fallback' => Sample::whereIn('sample_status', [
                'ready_for_delivery', 'interpretation_done', 'tested', 'completed',
            ])->whereNull('testing_completed_at')->whereBetween('updated_at', [$start, $end])->count(),
        ];

        $lhuByType = Document::whereIn('document_type', ['laporan_hasil_uji', 'lhu'])
            ->where('source', 'generated')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('document_type, COUNT(*) AS total')
            ->groupBy('document_type')
            ->orderBy('document_type')
            ->get();

        $storedCounts = $report ? data_get($report->report_data, 'iku.raw_counts') : null;
        $deltas = null;
        if (is_array($storedCounts)) {
            $deltas = [];
            foreach ($current['raw_counts'] as $key => $value) {
                $deltas[$key] = $value - (int) ($storedCounts[$key] ?? 0);
            }
        }

        $this->line(json_encode([
            'read_only' => true,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'report' => $report ? [
                'id' => $report->id,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'stored_iku' => data_get($report->report_data, 'iku.iku_value'),
                'stored_counts' => $storedCounts,
                'deltas' => $deltas,
            ] : null,
            'current_iku' => [
                'value' => $current['iku_value'],
                'category' => $current['iku_category'],
                'counts' => $current['raw_counts'],
            ],
            'current_operational_counts' => $freshStatistics,
            'submission_timestamp_sources' => $submissionTimestampBreakdown,
            'request_completion_timestamp_sources' => $timestampBreakdown,
            'sample_completion_timestamp_sources' => $sampleTimestampBreakdown,
            'lhu_documents_by_type' => $lhuByType,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
