<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SampleTestProcess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SampleProcessController extends Controller
{
    /**
     * Get process details for modal display.
     */
    public function show(SampleTestProcess $process): JsonResponse
    {
        $process->load(['sample.testRequest', 'analyst']);

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $process->id,
                'sample_id' => $process->sample_id,
                'sample_code' => $process->sample?->sample_code,
                'short_description' => $process->sample?->short_description,
                'stage' => $process->stage instanceof \App\Enums\TestProcessStage
                    ? $process->stage->value
                    : $process->stage,
                'stage_label' => $process->stage instanceof \App\Enums\TestProcessStage
                    ? $process->stage->label()
                    : ucfirst($process->stage),
                'performed_by' => $process->performed_by,
                'analyst_name' => $process->analyst?->name,
                'started_at' => $process->started_at?->toIso8601String(),
                'started_at_display' => $process->started_at?->format('d M Y H:i'),
                'completed_at' => $process->completed_at?->toIso8601String(),
                'completed_at_display' => $process->completed_at?->format('d M Y H:i'),
                'notes' => $process->notes,
                'is_started' => $process->started_at !== null,
                'is_completed' => $process->completed_at !== null,
            ],
        ]);
    }

    /**
     * Start a process (set started_at to now).
     */
    public function start(Request $request, SampleTestProcess $process): JsonResponse
    {
        if ($process->started_at !== null) {
            return response()->json([
                'ok' => false,
                'message' => 'Proses sudah dimulai sebelumnya.',
            ], 422);
        }

        $process->update([
            'started_at' => now(),
            'performed_by' => $request->user()?->id ?? $process->performed_by,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Proses berhasil dimulai.',
            'data' => [
                'started_at' => $process->started_at->toIso8601String(),
                'started_at_display' => $process->started_at->format('d M Y H:i'),
            ],
        ]);
    }

    /**
     * Complete a process (set completed_at to now).
     */
    public function complete(Request $request, SampleTestProcess $process): JsonResponse
    {
        if ($process->completed_at !== null) {
            return response()->json([
                'ok' => false,
                'message' => 'Proses sudah selesai sebelumnya.',
            ], 422);
        }

        // Auto-start if not started yet
        $updates = [
            'completed_at' => now(),
        ];

        if ($process->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($process->performed_by === null) {
            $updates['performed_by'] = $request->user()?->id;
        }

        $process->update($updates);

        return response()->json([
            'ok' => true,
            'message' => 'Proses berhasil diselesaikan.',
            'data' => [
                'started_at' => $process->started_at?->toIso8601String(),
                'started_at_display' => $process->started_at?->format('d M Y H:i'),
                'completed_at' => $process->completed_at->toIso8601String(),
                'completed_at_display' => $process->completed_at->format('d M Y H:i'),
            ],
        ]);
    }

    /**
     * Quick update notes only.
     */
    public function updateNotes(Request $request, SampleTestProcess $process): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $process->update([
            'notes' => $validated['notes'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Catatan berhasil diperbarui.',
        ]);
    }
}
