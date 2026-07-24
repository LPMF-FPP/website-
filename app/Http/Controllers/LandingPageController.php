<?php

namespace App\Http\Controllers;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\ChangelogService;
use App\Services\DashboardHeroStatsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class LandingPageController extends Controller
{
    public function __construct(
        private readonly ChangelogService $changelogService,
        private readonly DashboardHeroStatsService $dashboardHeroStatsService
    ) {}

    public function __invoke(): View
    {
        $latestVersion = $this->changelogService->getChangelogs()[0]['version'] ?? null;

        return view('landing', [
            'heroStatus' => $this->buildHeroStatus(),
            'stats' => $this->buildOperationalStats(),
            'footerVersion' => $latestVersion,
        ]);
    }

    private function buildHeroStatus(): array
    {
        $requestsTableReady = $this->tableExists('test_requests');
        $samplesTableReady = $this->tableExists('samples');

        $activeRequests = 0;
        $activeSamples = 0;

        if ($requestsTableReady) {
            $activeRequests = TestRequest::query()->whereNotIn('status', ['completed', 'rejected'])->count();
        }

        if ($samplesTableReady && $requestsTableReady) {
            $activeSamples = Sample::query()->whereHas('testRequest', function ($query) {
                $query->whereNotIn('status', ['completed', 'rejected']);
            })->count();
        }

        return [
            'label' => $requestsTableReady ? 'Data Operasional Tersedia' : 'Data Operasional Belum Tersedia',
            'detail' => $requestsTableReady
                ? sprintf('%d resi aktif · %d sampel aktif', $activeRequests, $activeSamples)
                : 'Menunggu sinkronisasi data laboratorium',
            'indicator' => $requestsTableReady ? 'online' : 'offline',
        ];
    }

    private function buildOperationalStats(): array
    {
        if (! $this->tableExists('test_requests') || ! $this->tableExists('samples') || ! $this->tableExists('users')) {
            return [
                'period_label' => 'Ringkasan Operasional (Data Belum Tersedia)',
                'items' => [
                    ['label' => 'Resi Aktif', 'value' => '0'],
                    ['label' => 'Sampel Terdaftar', 'value' => '0'],
                    ['label' => 'Rata-rata Proses', 'value' => '0 hr'],
                    ['label' => 'SLA ≤ 7 Hari', 'value' => '0%'],
                    ['label' => 'Resi Selesai', 'value' => '0'],
                    ['label' => 'User Aktif', 'value' => '0'],
                ],
            ];
        }

        $avgProcessing = $this->dashboardHeroStatsService->calculateMonthlyAverageProcessingDays();
        $customerSatisfaction = $this->dashboardHeroStatsService->calculateCustomerSatisfaction();
        $latestDataPoint = TestRequest::query()->latest('created_at')->value('created_at');
        $periodLabel = $latestDataPoint
            ? 'Ringkasan Operasional (s.d. '.Carbon::parse($latestDataPoint)->translatedFormat('d M Y').')'
            : 'Ringkasan Operasional';

        return [
            'period_label' => $periodLabel,
            'items' => [
                [
                    'label' => 'Resi Aktif',
                    'value' => number_format(TestRequest::query()->whereNotIn('status', ['completed', 'rejected'])->count(), 0, ',', '.'),
                ],
                [
                    'label' => 'Sampel Terdaftar',
                    'value' => number_format(Sample::query()->count(), 0, ',', '.'),
                ],
                [
                    'label' => 'Rata-rata Proses',
                    'value' => ($avgProcessing['average'] !== null && $avgProcessing['count'] > 0)
                        ? number_format($avgProcessing['average'], 1, ',', '.').' hari/permintaan'
                        : 'Belum ada data',
                ],
                [
                    'label' => 'Kepuasan Pengguna',
                    'value' => $customerSatisfaction['total_responses'] > 0
                        ? number_format($customerSatisfaction['score'], 2, ',', '.').'/4'
                        : 'Belum ada data',
                ],
                [
                    'label' => 'Resi Selesai',
                    'value' => number_format(TestRequest::query()->where('status', 'completed')->count(), 0, ',', '.'),
                ],
                [
                    'label' => 'User Aktif',
                    'value' => number_format(User::query()->where('is_active', true)->count(), 0, ',', '.'),
                ],
            ],
        ];
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (QueryException) {
            return false;
        }
    }
}
