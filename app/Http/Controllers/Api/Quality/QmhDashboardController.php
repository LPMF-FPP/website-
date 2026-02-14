<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhDocumentRevision;
use App\Services\Quality\QmhDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QmhDashboardController extends Controller
{
    public function stats(Request $request, QmhDashboardService $service): JsonResponse
    {
        $data = $service->getPulseStats();

        $userId = $request->user()->id;
        $data['user_tasks'] = QmhDocumentRevision::where('status', 'in_review')
            ->where('diperiksa_oleh', $userId)
            ->count();

        $approvals = QmhDocumentRevision::whereIn('status', ['approved_by_reviewer', 'in_approval'])
            ->where('disahkan_oleh', $userId)
            ->count();
        $data['user_tasks'] += $approvals;

        return response()->json($data);
    }

    public function tips(Request $request): JsonResponse
    {
        $clause = (int) $request->input('clause');
        $tips = config("iso17025.tips.{$clause}");

        if (! $tips) {
            return response()->json([
                'requirement' => 'Tips tidak tersedia untuk klausul ini.',
                'checklist' => [],
            ]);
        }

        return response()->json($tips);
    }
}
