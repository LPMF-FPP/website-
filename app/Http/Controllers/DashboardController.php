<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TestRequest;
use App\Models\Sample;
use App\Models\TestResult;
use App\Models\Investigator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            // 1. Hitung statistik utama dari database
            $totalRequests = TestRequest::count();
            $pendingSamples = Sample::whereHas('testRequest', function($query) {
                $query->whereIn('status', ['submitted', 'verified', 'received']);
            })->count();
            $completedTests = TestRequest::where('status', 'completed')->count();

            // 2. Hitung SLA Performance (contoh: target 7 hari)
            $slaPerformance = $this->calculateSLAPerformance();

            // 3. Aktivitas terbaru (5 terakhir)
            $recentActivities = $this->getRecentActivities();

            // 4. Status breakdown
            $statusBreakdown = TestRequest::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status')
                ->toArray();

            $dashboardData = [
                'stats' => [
                    'total_requests' => $totalRequests,
                    'pending_samples' => $pendingSamples,
                    'completed_tests' => $completedTests,
                    'sla_performance' => $slaPerformance
                ],
                'recent_activities' => $recentActivities,
                'status_breakdown' => $statusBreakdown,
            ];

        } catch (\Exception $e) {
            // Fallback jika database belum siap atau ada error
            $dashboardData = [
                'stats' => [
                    'total_requests' => 0,
                    'pending_samples' => 0,
                    'completed_tests' => 0,
                    'sla_performance' => 0
                ],
                'recent_activities' => collect([]),
                'status_breakdown' => [],
            ];
        }

        return view('dashboard', $dashboardData);
    }

    private function calculateSLAPerformance()
    {
        try {
            $completed = TestRequest::where('status', 'completed')
                ->whereNotNull('completed_at')
                ->get();

            if ($completed->isEmpty()) {
                return 0;
            }

            $onTime = $completed->filter(function ($request) {
                $days = $request->created_at->diffInDays($request->completed_at);
                return $days <= 7; // Target 7 hari
            })->count();

            return round(($onTime / $completed->count()) * 100);
        } catch (\Exception $e) {
            return 0;
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
                        'title' => 'Permintaan Baru: ' . $request->request_number,
                        'description' => 'dari ' . ($request->investigator->name ?? 'Unknown'),
                        'time' => $request->created_at,
                        'icon' => '📋',
                        'color' => 'blue'
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
                            'title' => 'Hasil Test: ' . $result->sample->sample_name,
                            'description' => 'Status: ' . $result->result_status,
                            'time' => $result->created_at,
                            'icon' => '🧪',
                            'color' => 'green'
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
            return response()->json([
                'total_requests' => TestRequest::count(),
                'pending_samples' => Sample::whereHas('testRequest', function($query) {
                    $query->whereIn('status', ['submitted', 'verified', 'received']);
                })->count(),
                'completed_tests' => TestRequest::where('status', 'completed')->count(),
                'sla_performance' => $this->calculateSLAPerformance()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'total_requests' => 0,
                'pending_samples' => 0,
                'completed_tests' => 0,
                'sla_performance' => 0
            ]);
        }
    }
}
