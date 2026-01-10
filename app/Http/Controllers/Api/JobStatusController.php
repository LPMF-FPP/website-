<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobStatus;
use Illuminate\Http\JsonResponse;

class JobStatusController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $job = JobStatus::find($id);

        if (! $job) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Job not found',
            ], 404);
        }

        return response()->json([
            'id' => $job->id,
            'type' => $job->type,
            'status' => $job->status,
            'result' => $job->result,
            'error' => $job->error,
            'progress' => [
                'current' => $job->progress_current,
                'total' => $job->progress_total,
                'percentage' => $job->progress_total > 0 
                    ? round(($job->progress_current / $job->progress_total) * 100, 2)
                    : 0,
            ],
            'created_at' => $job->created_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
        ]);
    }
}
