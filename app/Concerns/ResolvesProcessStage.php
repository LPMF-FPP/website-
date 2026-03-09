<?php

namespace App\Concerns;

use App\Enums\TestProcessStage;
use App\Models\RecentRequest;
use App\Models\Sample;
use App\Models\TestRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared logic for resolving the "current stage" of each sample,
 * building the stepper UI state, and pagination helpers.
 *
 * Used by ProcessController (web) and Pengujian\Workbench (Livewire).
 */
trait ResolvesProcessStage
{
    /**
     * Normalise a stage value to a plain string regardless of whether
     * the underlying column is cast to the TestProcessStage enum.
     */
    private function stageValue($stage): ?string
    {
        if ($stage instanceof TestProcessStage) {
            return $stage->value;
        }

        return $stage ? (string) $stage : null;
    }

    /**
     * Enrich a collection of Sample models with computed attributes
     * that describe the "current" process, stage label, status key/label,
     * and schedule.
     */
    private function mapSamplesWithProcessState(Collection $samples): Collection
    {
        $stageOrder = [
            TestProcessStage::ADMINISTRATION->value => 0,
            TestProcessStage::PREPARATION->value => 1,
            TestProcessStage::INSTRUMENTATION->value => 2,
            TestProcessStage::INTERPRETATION->value => 3,
        ];

        $stageSequence = [
            TestProcessStage::PREPARATION->value,
            TestProcessStage::INSTRUMENTATION->value,
            TestProcessStage::INTERPRETATION->value,
        ];

        return $samples->map(function (Sample $sample) use ($stageOrder, $stageSequence) {
            $processes = $sample->testProcesses
                ->sortBy(fn ($process) => $stageOrder[$this->stageValue($process->stage ?? null)] ?? 99)
                ->values();

            // First, check for any in-progress process
            $currentProcess = $processes->first(fn ($process) => $process->started_at && ! $process->completed_at);

            $statusKey = 'pending';
            $statusLabel = 'Belum dimulai';
            $nextStageLabel = null;

            if ($currentProcess) {
                // There's an in-progress process
                $statusKey = 'in_progress';
                $statusLabel = 'Berjalan';
            } else {
                // No in-progress process — determine next stage based on completed processes
                $completedStages = $processes
                    ->filter(fn ($p) => $p->completed_at)
                    ->map(fn ($p) => $this->stageValue($p->stage))
                    ->unique()
                    ->values()
                    ->toArray();

                $highestCompletedIndex = -1;
                foreach ($stageSequence as $index => $stage) {
                    if (in_array($stage, $completedStages, true)) {
                        $highestCompletedIndex = $index;
                    }
                }

                $nextStageIndex = $highestCompletedIndex + 1;

                if ($nextStageIndex < count($stageSequence)) {
                    $nextStageValue = $stageSequence[$nextStageIndex];

                    $nextStageProcess = $processes->first(
                        fn ($p) => $this->stageValue($p->stage) === $nextStageValue
                    );

                    if ($nextStageProcess) {
                        $currentProcess = $nextStageProcess;
                        if ($nextStageProcess->completed_at) {
                            $statusKey = 'completed';
                            $statusLabel = 'Selesai';
                        } else {
                            $statusKey = 'pending';
                            $statusLabel = 'Menunggu';
                        }
                    } else {
                        $currentProcess = $processes->sortByDesc('completed_at')->first();
                        $statusKey = 'pending';
                        $statusLabel = 'Menunggu';
                        $nextStageLabel = TestProcessStage::tryFrom($nextStageValue)?->label();
                    }
                } else {
                    // All stages completed — pick the highest stage in logical order
                    $currentProcess = $processes
                        ->sortByDesc(fn ($p) => $stageOrder[$this->stageValue($p->stage)] ?? 0)
                        ->first();
                    if ($currentProcess?->completed_at) {
                        $statusKey = 'completed';
                        $statusLabel = 'Selesai';
                    }
                }
            }

            // Fallback if still no current process
            if (! $currentProcess) {
                $currentProcess = $processes->first();
            }

            $scheduledAt = null;
            if ($currentProcess && ! empty($currentProcess->metadata['scheduled_at'])) {
                $scheduledAt = Carbon::parse($currentProcess->metadata['scheduled_at']);
            }

            $sample->current_process = $currentProcess;
            $sample->current_stage_label = $nextStageLabel ?? ($currentProcess?->stage_label ?? '—');
            $sample->current_stage_value = $this->stageValue($currentProcess?->stage ?? null);
            $sample->current_status_key = $statusKey;
            $sample->current_status_label = $statusLabel;
            $sample->current_schedule = $sample->test_date ?? $scheduledAt ?? $currentProcess?->started_at;

            return $sample;
        });
    }

    /**
     * Paginate a plain Collection into a LengthAwarePaginator.
     */
    private function paginateCollection(Collection $items, int $perPage, array $query = []): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $items->values();

        return new LengthAwarePaginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => url()->current(),
                'query' => $query,
            ]
        );
    }

    /**
     * Touch the "recently viewed" record for the given request/user pair.
     * Keeps only the 10 most recent entries per user.
     */
    private function touchRecentRequest(TestRequest $testRequest, $user): void
    {
        if (! $user) {
            return;
        }

        RecentRequest::updateOrCreate(
            [
                'user_id' => $user->id,
                'test_request_id' => $testRequest->id,
            ],
            ['last_opened_at' => now()]
        );

        $keepIds = RecentRequest::where('user_id', $user->id)
            ->orderByDesc('last_opened_at')
            ->limit(10)
            ->pluck('id');

        RecentRequest::where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Check if a TestRequest is ready for delivery based on interpretation status.
     */
    private function isReadyForDelivery(TestRequest $testRequest, Collection $samples): bool
    {
        if ($samples->isEmpty()) {
            return false;
        }

        if (in_array($testRequest->status, ['ready_for_delivery', 'completed'], true)) {
            return false;
        }

        $allProcesses = $samples->flatMap(fn (Sample $s) => $s->testProcesses);
        $interpretationProcesses = $allProcesses->filter(
            fn ($p) => $this->stageValue($p->stage) === TestProcessStage::INTERPRETATION->value
        );

        if ($interpretationProcesses->isEmpty()) {
            return false;
        }

        return $interpretationProcesses->every(fn ($p) => $p->completed_at);
    }
}
