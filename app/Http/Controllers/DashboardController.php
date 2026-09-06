<?php

namespace App\Http\Controllers;

use App\Models\GuestVisit;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\TestResult;
use App\Services\DashboardHeroStatsService;
use App\Services\DisposisiTableService;
use App\Services\EnvironmentMonitoringService;
use App\Services\IkuService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly IkuService $ikuService,
        private readonly EnvironmentMonitoringService $environmentService,
        private readonly DisposisiTableService $disposisiService,
        private readonly DashboardHeroStatsService $dashboardHeroStatsService
    ) {}

    public function index()
    {
        try {
            // 1. Hitung statistik utama dari database
            $totalRequests = TestRequest::count();
            $pendingRequests = TestRequest::whereIn('status', ['submitted', 'verified', 'received'])->count();
            $completedTests = TestRequest::where('status', 'completed')->count();

            // 2. Hitung IKU Performance
            $ikuData = $this->calculateIkuPerformance();

            // 3. Aktivitas terbaru (5 terakhir)
            $recentActivities = $this->getRecentActivities();

            // 4. Status breakdown
            $statusBreakdown = TestRequest::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status')
                ->toArray();

            // 5. Environment monitoring due tasks
            $environmentMonitoring = $this->getEnvironmentMonitoringData();

            // 6. Rata-rata kecepatan pengerjaan bulan ini
            $avgProcessing = $this->dashboardHeroStatsService->calculateMonthlyAverageProcessingDays();

            // 7. Kepuasan Pengguna
            $customerSatisfaction = $this->dashboardHeroStatsService->calculateCustomerSatisfaction();

            // 8. Disposisi Table Data
            $disposisiData = $this->disposisiService->getPaginatedTableData(
                filters: ['search' => request('disposisi_search')],
                perPage: 15
            );

            $dashboardData = [
                'stats' => [
                    'total_requests' => $totalRequests,
                    'pending_requests' => $pendingRequests,
                    'completed_tests' => $completedTests,
                    'iku_value' => $ikuData['iku_value'],
                    'iku_category' => $ikuData['iku_category'],
                ],
                'iku_data' => $ikuData,
                'recent_activities' => $recentActivities,
                'status_breakdown' => $statusBreakdown,
                'environment_monitoring' => $environmentMonitoring,
                'avg_processing' => $avgProcessing,
                'customer_satisfaction' => $customerSatisfaction,
                'disposisi_table' => $disposisiData,
                'guest_book_today' => $this->getGuestBookToday(),
            ];

        } catch (\Exception $e) {
            // Fallback jika database belum siap atau ada error
            $dashboardData = [
                'stats' => [
                    'total_requests' => 0,
                    'pending_requests' => 0,
                    'completed_tests' => 0,
                    'iku_value' => 0,
                    'iku_category' => 'F',
                ],
                'iku_data' => null,
                'recent_activities' => collect([]),
                'status_breakdown' => [],
                'environment_monitoring' => [
                    'enabled' => false,
                    'due_locations' => collect([]),
                    'is_work_day' => false,
                    'active_window' => null,
                ],
                'avg_processing' => ['average' => null, 'count' => 0],
                'customer_satisfaction' => [
                    'score' => 0,
                    'percentage' => 0,
                    'total_responses' => 0,
                    'trend' => 0,
                    'trend_direction' => 'stable',
                ],
                'disposisi_table' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'guest_book_today' => [
                    'total' => 0,
                    'active' => 0,
                    'checked_out' => 0,
                    'latest' => collect([]),
                ],
            ];
        }

        return view('dashboard', $dashboardData);
    }

    private function calculateIkuPerformance(): array
    {
        try {
            $config = $this->ikuService->getConfig();
            if (! $config['enabled']) {
                return [
                    'iku_value' => 0,
                    'iku_category' => '-',
                    'enabled' => false,
                ];
            }

            return array_merge(
                $this->ikuService->computeForCurrentMonth(),
                ['enabled' => true]
            );
        } catch (\Exception $e) {
            return [
                'iku_value' => 0,
                'iku_category' => 'F',
                'enabled' => false,
            ];
        }
    }

    private function getRecentActivities()
    {
        try {
            // Ambil aktivitas dari berbagai tabel
            $activities = collect();

            // Permintaan baru
            $newRequests = TestRequest::with('investigator')
                ->latest()
                ->take(3)
                ->get()
                ->map(function ($request) {
                    return (object) [
                        'type' => 'new_request',
                        'title' => 'Permintaan Baru: '.($request->receipt_number ?? $request->request_number),
                        'description' => 'dari '.($request->investigator->name ?? 'Unknown'),
                        'time' => $request->created_at,
                        'icon' => '📋',
                        'color' => 'blue',
                    ];
                });

            // Test results jika ada
            if (class_exists('App\Models\TestResult')) {
                $newResults = TestResult::with('sample.testRequest')
                    ->latest()
                    ->take(2)
                    ->get()
                    ->map(function ($result) {
                        return (object) [
                            'type' => 'test_result',
                            'title' => 'Hasil Test: '.$result->sample->short_description,
                            'description' => 'Status: '.$result->result_status,
                            'time' => $result->created_at,
                            'icon' => '🧪',
                            'color' => 'green',
                        ];
                    });

                $activities = $activities->concat($newResults);
            }

            return $activities->concat($newRequests)
                ->sortByDesc('time')
                ->take(5)
                ->values();

        } catch (\Exception $e) {
            return collect([]);
        }
    }

    // API endpoint untuk real-time updates
    public function getStats()
    {
        try {
            $ikuData = $this->calculateIkuPerformance();

            return response()->json([
                'total_requests' => TestRequest::count(),
                'total_samples' => Sample::count(),
                'pending_samples' => Sample::whereHas('testRequest', function ($query) {
                    $query->whereIn('status', ['submitted', 'verified', 'received']);
                })->count(),
                'completed_tests' => TestRequest::where('status', 'completed')->count(),
                'iku_value' => $ikuData['iku_value'],
                'iku_category' => $ikuData['iku_category'],
                'iku_data' => $ikuData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'total_requests' => 0,
                'total_samples' => 0,
                'pending_samples' => 0,
                'completed_tests' => 0,
                'iku_value' => 0,
                'iku_category' => 'F',
                'iku_data' => null,
            ]);
        }
    }

    private function getEnvironmentMonitoringData(): array
    {
        try {
            if (! $this->environmentService->isEnabled()) {
                return [
                    'enabled' => false,
                    'due_locations' => collect([]),
                    'is_work_day' => false,
                    'active_window' => null,
                ];
            }

            $now = \Carbon\Carbon::now();
            $user = auth()->user();
            $isWorkDay = $this->environmentService->isWorkDay($now);
            $activeWindow = $this->environmentService->getActiveWindow($now);
            $dueList = $this->environmentService->getDueListForUser($user, $now);

            return [
                'enabled' => true,
                'due_locations' => $dueList,
                'is_work_day' => $isWorkDay,
                'active_window' => $activeWindow,
            ];
        } catch (\Exception $e) {
            return [
                'enabled' => false,
                'due_locations' => collect([]),
                'is_work_day' => false,
                'active_window' => null,
            ];
        }
    }

    private function getGuestBookToday(): array
    {
        try {
            $today = today()->toDateString();
            $visits = GuestVisit::with('investigator')
                ->withCount('items')
                ->where('visit_date', $today)
                ->orderBy('visit_time', 'desc')
                ->get();

            return [
                'total' => $visits->count(),
                'active' => $visits->where('status', 'active')->count(),
                'checked_out' => $visits->where('status', 'checked_out')->count(),
                'latest' => $visits->take(6),
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard guest book today query failed', ['error' => $e->getMessage()]);

            return [
                'total' => 0,
                'active' => 0,
                'checked_out' => 0,
                'latest' => collect([]),
            ];
        }
    }
}
