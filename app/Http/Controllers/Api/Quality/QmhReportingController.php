<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\QmhReportFilterRequest;
use App\Http\Requests\Quality\QmhSummaryFilterRequest;
use App\Services\Quality\QmhDashboardSummaryService;
use App\Services\Quality\QmhReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class QmhReportingController extends Controller
{
    public function summary(QmhSummaryFilterRequest $request, QmhDashboardSummaryService $service): JsonResponse
    {
        $summary = $service->summarize($request->validated());

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function revisionHistory(QmhReportFilterRequest $request, QmhReportingService $service): JsonResponse
    {
        return response()->json($service->revisionHistory($request->validated()));
    }

    public function downloadHistory(QmhReportFilterRequest $request, QmhReportingService $service): JsonResponse
    {
        return response()->json($service->downloadHistory($request->validated()));
    }

    public function controlledDistribution(QmhReportFilterRequest $request, QmhReportingService $service): JsonResponse
    {
        return response()->json($service->downloadHistory($request->validated(), true));
    }

    public function revisionHistoryExport(QmhReportFilterRequest $request, QmhReportingService $service): Response
    {
        $csv = $service->exportRevisionHistoryCsv($request->validated(), (string) $request->query('tz', 'Asia/Jakarta'));

        return response($csv['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csv['filename'].'"',
        ]);
    }

    public function downloadHistoryExport(QmhReportFilterRequest $request, QmhReportingService $service): Response
    {
        $csv = $service->exportDownloadHistoryCsv($request->validated(), (string) $request->query('tz', 'Asia/Jakarta'));

        return response($csv['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csv['filename'].'"',
        ]);
    }

    public function controlledDistributionExport(QmhReportFilterRequest $request, QmhReportingService $service): Response
    {
        $csv = $service->exportDownloadHistoryCsv($request->validated(), (string) $request->query('tz', 'Asia/Jakarta'), true);

        return response($csv['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csv['filename'].'"',
        ]);
    }
}
