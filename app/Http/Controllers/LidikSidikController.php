<?php

namespace App\Http\Controllers;

use App\Models\Sample;
use App\Models\SurveyResponse;
use App\Models\TestRequest;
use App\Services\ActiveSubstanceService;

class LidikSidikController extends Controller
{
    protected ActiveSubstanceService $activeSubstanceService;

    public function __construct(ActiveSubstanceService $activeSubstanceService)
    {
        $this->activeSubstanceService = $activeSubstanceService;
    }

    public function index()
    {
        $activeSubstanceStats = $this->activeSubstanceService->breakdown(0);

        $stats = [
            'total_requests' => TestRequest::count(),
            'pending_requests' => TestRequest::where('status', 'submitted')->count(),
            'in_progress' => TestRequest::whereIn('status', ['in_testing', 'analysis', 'quality_check'])->count(),
            'completed' => TestRequest::whereIn('status', ['completed', 'ready_for_delivery'])->count(),
            'completed_this_week' => TestRequest::whereIn('status', ['completed', 'ready_for_delivery'])
                ->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'samples_this_month' => Sample::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'active_substances_found' => count($activeSubstanceStats['labels']),
        ];

        $recentRequests = TestRequest::with([
                'investigator:id,name,jurisdiction,rank',
                'samples:id,test_request_id',
            ])
            ->withCount('samples')
            ->latest()
            ->limit(5)
            ->get();

        $processingDurations = TestRequest::whereNotNull('submitted_at')
            ->whereNotNull('completed_at')
            ->get()
            ->map(static function (TestRequest $request) {
                if (!$request->submitted_at || !$request->completed_at) {
                    return null;
                }

                return $request->submitted_at->diffInMinutes($request->completed_at);
            })
            ->filter();

        $averageProcessingDays = $processingDurations->isNotEmpty()
            ? round($processingDurations->avg() / 1440, 1)
            : null;

        $averageSatisfaction = SurveyResponse::avg('overall_satisfaction');

        $metrics = [
            'monthly_target' => 50,
            'avg_processing_time' => $averageProcessingDays,
            'satisfaction_score' => $averageSatisfaction ? round($averageSatisfaction, 1) : null,
            'active_substances_fallback' => $activeSubstanceStats['fallback'] ?? false,
            'unique_active_substances' => count($activeSubstanceStats['labels']),
            'total_active_substances' => $activeSubstanceStats['total'],
        ];

        return view('lidik-sidik.index', [
            'stats' => $stats,
            'recentRequests' => $recentRequests,
            'metrics' => $metrics,
            'activeSubstanceStats' => $activeSubstanceStats,
        ]);
    }
}
