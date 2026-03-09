<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SampleTestProcess;
use App\Services\Quality\AuditTrailService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SampleProcessController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Get process details for modal display.
     */
    public function show(SampleTestProcess $process): JsonResponse
    {
        $process->load(['sample.testRequest', 'analyst']);

        $start = $this->workflowService->canStartProcess($process);
        $complete = $this->workflowService->canCompleteProcess($process);
        $unlock = $this->workflowService->canUnlockProcess($process);

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
                'can_start' => $start['allowed'],
                'start_reason' => $start['reason'],
                'can_complete' => $complete['allowed'],
                'complete_reason' => $complete['reason'],
                'can_unlock' => $unlock['allowed'],
                'unlock_reason' => $unlock['reason'],
            ],
        ]);
    }

    /**
     * Start a process (set started_at to now).
     */
    public function start(Request $request, SampleTestProcess $process): JsonResponse
    {
        try {
            $before = $process->toArray();
            $this->workflowService->startExistingProcess($process);
            $process->refresh();

            $this->auditTrailService->log(
                tableName: 'sample_test_processes',
                recordId: $process->id,
                action: 'process_started',
                oldValues: $before,
                newValues: $process->toArray(),
                changedBy: $request->user()?->id,
                reason: 'Mulai tahap dari quick action pengujian'
            );
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Tahap berhasil dimulai.',
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
        try {
            $before = $process->toArray();
            $nextProcess = $this->workflowService->completeTestProcess($process);
            $process->refresh();

            $this->auditTrailService->log(
                tableName: 'sample_test_processes',
                recordId: $process->id,
                action: 'process_completed',
                oldValues: $before,
                newValues: $process->toArray(),
                changedBy: $request->user()?->id,
                reason: 'Selesaikan tahap dari quick action pengujian'
            );
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Tahap berhasil diselesaikan.',
            'data' => [
                'started_at' => $process->started_at?->toIso8601String(),
                'started_at_display' => $process->started_at?->format('d M Y H:i'),
                'completed_at' => $process->completed_at->toIso8601String(),
                'completed_at_display' => $process->completed_at->format('d M Y H:i'),
                'next_process_id' => $nextProcess?->id,
                'next_stage' => $nextProcess?->stage instanceof \BackedEnum
                    ? $nextProcess->stage->value
                    : $nextProcess?->stage,
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

    public function unlock(Request $request, SampleTestProcess $process): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $before = $process->toArray();
            $this->workflowService->unlockCompletedProcess($process, $validated['reason']);
            $process->refresh();

            $this->auditTrailService->log(
                tableName: 'sample_test_processes',
                recordId: $process->id,
                action: 'process_unlocked',
                oldValues: $before,
                newValues: $process->toArray(),
                changedBy: $request->user()?->id,
                reason: $validated['reason']
            );
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Tahap berhasil diperbaiki dengan alasan tercatat.',
            'data' => [
                'started_at' => $process->started_at?->toIso8601String(),
                'completed_at' => $process->completed_at?->toIso8601String(),
            ],
        ]);
    }
}
