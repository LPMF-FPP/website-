<?php

namespace App\Services;

use App\Models\CustomerSurvey;
use App\Models\TestRequest;
use Carbon\Carbon;

class DashboardHeroStatsService
{
    public function calculateMonthlyAverageProcessingDays(): array
    {
        try {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();

            $requests = TestRequest::whereIn('status', ['ready_for_delivery', 'completed'])
                ->whereNotNull('submitted_at')
                ->whereNotNull('ready_for_delivery_at')
                ->whereBetween('ready_for_delivery_at', [$startOfMonth, $endOfMonth])
                ->get(['submitted_at', 'ready_for_delivery_at']);

            if ($requests->isEmpty()) {
                return ['average' => null, 'count' => 0];
            }

            $totalDays = $requests->sum(function ($request) {
                $startDate = Carbon::parse($request->submitted_at);
                $endDate = Carbon::parse($request->ready_for_delivery_at);

                if ($endDate->lt($startDate)) {
                    return 0;
                }

                return $startDate->diffInWeekdays($endDate);
            });

            return [
                'average' => round($totalDays / $requests->count(), 1),
                'count' => $requests->count(),
            ];
        } catch (\Exception) {
            return ['average' => null, 'count' => 0];
        }
    }

    public function calculateCustomerSatisfaction(): array
    {
        try {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();

            $currentMonth = CustomerSurvey::whereBetween('submitted_at', [$startOfMonth, $endOfMonth]);
            $avgScore = $currentMonth->avg('score_avg') ?? 0;
            $totalResponses = $currentMonth->count();

            $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
            $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
            $lastMonthAvg = CustomerSurvey::whereBetween('submitted_at', [$lastMonthStart, $lastMonthEnd])
                ->avg('score_avg') ?? 0;

            $percentage = $avgScore > 0 ? (($avgScore - 1) / 3) * 100 : 0;

            if ($lastMonthAvg > 0) {
                $trend = $avgScore - $lastMonthAvg;
                $trendDirection = $trend > 0.01 ? 'up' : ($trend < -0.01 ? 'down' : 'stable');
                $trendValue = round(abs($trend), 2);
            } else {
                $trendValue = null;
                $trendDirection = 'new';
            }

            return [
                'score' => round($avgScore, 2),
                'percentage' => round($percentage, 1),
                'total_responses' => $totalResponses,
                'trend' => $trendValue,
                'trend_direction' => $trendDirection,
            ];
        } catch (\Exception) {
            return [
                'score' => 0,
                'percentage' => 0,
                'total_responses' => 0,
                'trend' => 0,
                'trend_direction' => 'stable',
            ];
        }
    }
}
