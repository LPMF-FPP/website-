<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesProcessStage;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProcessController extends Controller
{
    use ResolvesProcessStage;

    public function index(Request $request): View
    {
        // The index view delegates entirely to <livewire:pengujian.workbench />,
        // so no data needs to be passed from this controller.
        return view('process.index');
    }

    public function show(Request $request, TestRequest $testRequest, LabelService $labelService): View
    {
        $labelService->ensureAutoRemainingUnitsForRequest($testRequest, $request->user()?->id);

        $testRequest->load([
            'investigator',
            'samples',
            'evidenceUnits.remainingUnits',
            'parentTestRequest',
            'delivery.reopenings.reopenedBy',
            'delivery.reopenings.sample',
        ]);

        $this->touchRecentRequest($testRequest, $request->user());

        // Database-level pagination instead of in-memory
        $paginatedRaw = Sample::with(['testProcesses'])
            ->where('test_request_id', $testRequest->id)
            ->orderBy('id')
            ->paginate(10);

        // Map process state onto the paginated items
        $rows = $this->mapSamplesWithProcessState($paginatedRaw->getCollection());
        $rows->each(function (Sample $sample) use ($testRequest): void {
            $sample->setAttribute('needs_additional_review', $this->isAwaitingAdditionalReview($testRequest, $sample));
        });
        $paginatedSamples = $paginatedRaw->setCollection($rows);

        // Use all samples (not just current page) for stepper & readiness checks
        $allSamples = $testRequest->samples->load('testProcesses');

        $hasProcesses = $allSamples->flatMap(function ($sample) {
            return $sample->testProcesses;
        })->isNotEmpty();

        $currentStageKey = $this->resolveStepperStage($testRequest, $allSamples);

        $readyForDelivery = $this->isReadyForDelivery($testRequest, $allSamples);
        $additionalReviewCount = in_array($testRequest->status, ['in_testing', 'analysis', 'quality_check'], true)
            ? $allSamples->filter(fn (Sample $sample) => $this->isAwaitingAdditionalReview($testRequest, $sample))->count()
            : 0;
        $handoverHistory = $testRequest->delivery?->reopenings->map(function ($reopening) use ($testRequest): array {
            $documents = \App\Models\Document::query()
                ->whereIn('id', $reopening->superseded_document_ids ?? [])
                ->where('document_type', 'ba_penyerahan')
                ->get();

            return [
                'cycle' => max(1, (int) $reopening->handover_cycle - 1),
                'reopened_at' => $reopening->reopened_at,
                'reopened_by' => $reopening->reopenedBy?->name ?? 'Petugas',
                'sample_code' => $reopening->sample?->sample_code ?? '-',
                'reason' => $reopening->reason,
                'documents' => $documents,
                'delivery' => $testRequest->delivery,
            ];
        }) ?? collect();

        return view('process.show', [
            'testRequest' => $testRequest,
            'samples' => $paginatedSamples,
            'hasProcesses' => $hasProcesses,
            'stepper' => $this->buildStepper($currentStageKey, $allSamples),
            'readyForDelivery' => $readyForDelivery,
            'additionalReviewCount' => $additionalReviewCount,
            'handoverHistory' => $handoverHistory,
        ]);
    }

    private function isAwaitingAdditionalReview(TestRequest $testRequest, Sample $sample): bool
    {
        return in_array($testRequest->status, ['in_testing', 'analysis', 'quality_check'], true)
            && $sample->status !== \App\Enums\SampleStatus::READY_FOR_DELIVERY->value
            && $sample->disposal_id === null
            && $sample->testProcesses->isEmpty();
    }

    public function markReadyForDelivery(TestRequest $testRequest): RedirectResponse
    {
        $requiredStages = ['preparation', 'instrumentation', 'interpretation'];
        $incompleteSamples = DB::transaction(function () use ($testRequest, $requiredStages): array {
            $lockedRequest = TestRequest::query()->lockForUpdate()->findOrFail($testRequest->id);
            if (! in_array($lockedRequest->status, ['in_testing', 'analysis', 'quality_check'], true)) {
                throw ValidationException::withMessages([
                    'error' => 'Permintaan harus berada dalam tahap pengujian sebelum dikirim ke penyerahan.',
                ]);
            }

            $samples = $lockedRequest->samples()
                ->select('id', 'sample_code', 'test_request_id')
                ->with(['testProcesses' => fn ($query) => $query->select('id', 'sample_id', 'stage', 'completed_at')])
                ->lockForUpdate()
                ->get();

            $incomplete = [];
            foreach ($samples as $sample) {
                $completedStages = $sample->testProcesses
                    ->filter(fn ($process) => $process->completed_at !== null)
                    ->map(fn ($process) => $this->stageValue($process->stage))
                    ->filter(fn ($stage) => $stage !== null && in_array($stage, $requiredStages, true))
                    ->unique()
                    ->values()
                    ->toArray();
                $missingStages = array_values(array_diff($requiredStages, $completedStages));

                if ($missingStages !== []) {
                    $label = $sample->sample_code ?: 'Sampel ID:'.$sample->id;
                    $incomplete[] = $label.' (belum: '.implode(', ', $missingStages).')';
                }
            }

            if ($incomplete !== []) {
                return $incomplete;
            }

            $lockedRequest->update([
                'status' => 'ready_for_delivery',
                'ready_for_delivery_at' => now(),
            ]);
            $lockedRequest->samples()->update([
                'status' => 'ready_for_delivery',
                'sample_status' => 'ready_for_delivery',
            ]);

            $delivery = Delivery::query()->where('request_id', $lockedRequest->id)->lockForUpdate()->first();
            if (! $delivery) {
                $delivery = Delivery::query()->create([
                    'request_id' => $lockedRequest->id,
                    'delivered_by' => auth()->id() ?? $lockedRequest->user_id,
                    'delivery_date' => now(),
                    'status' => DeliveryStatus::PENDING,
                ]);
            } elseif (! in_array($delivery->status, [DeliveryStatus::PENDING, DeliveryStatus::REOPENED, DeliveryStatus::READY], true)) {
                throw ValidationException::withMessages([
                    'error' => 'Status penyerahan berubah dan tidak dapat dimulai ulang dengan aman.',
                ]);
            }

            if ($delivery->status === DeliveryStatus::REOPENED) {
                $delivery->status = DeliveryStatus::PENDING;
            }
            if ($delivery->status === DeliveryStatus::PENDING) {
                if (! $delivery->status->canTransitionTo(DeliveryStatus::READY)) {
                    throw ValidationException::withMessages([
                        'error' => 'Status penyerahan tidak dapat dimulai kembali.',
                    ]);
                }
                $delivery->status = DeliveryStatus::READY;
            }
            $delivery->save();

            return [];
        });

        if ($incompleteSamples !== []) {
            return back()->withErrors([
                'error' => 'Tidak dapat mengirim ke penyerahan. Sampel berikut belum lengkap: '.implode('; ', $incompleteSamples),
            ]);
        }

        return redirect()
            ->route('delivery.show', $testRequest)
            ->with('success', 'Permintaan berhasil dikirim ke penyerahan.');
    }

    // storeProcess() removed — dead code with no route binding

    private function buildStepper(string $currentStageKey, ?Collection $allSamples = null): array
    {
        $totalSamples = $allSamples ? $allSamples->count() : 0;

        // Count completed samples per stage
        $stageCounts = [];
        if ($allSamples && $totalSamples > 0) {
            foreach (['preparation', 'instrumentation', 'interpretation'] as $stage) {
                $stageCounts[$stage] = $allSamples->filter(function ($sample) use ($stage) {
                    return $sample->testProcesses->contains(function ($p) use ($stage) {
                        return $this->stageValue($p->stage) === $stage && $p->completed_at !== null;
                    });
                })->count();
            }
        }

        $steps = [
            ['key' => 'submitted', 'label' => 'Dikirim'],
            ['key' => 'preparation', 'label' => 'Preparasi'],
            ['key' => 'instrumentation', 'label' => 'Instrumen'],
            ['key' => 'interpretation', 'label' => 'Interpretasi'],
            ['key' => 'ready_for_delivery', 'label' => 'Siap Diserahkan'],
        ];

        $activeIndex = collect($steps)->search(function ($step) use ($currentStageKey) {
            return $step['key'] === $currentStageKey;
        });

        if ($activeIndex === false) {
            $activeIndex = 0;
        }

        foreach ($steps as $index => $step) {
            if ($index < $activeIndex) {
                $steps[$index]['state'] = 'completed';
            } elseif ($index === $activeIndex) {
                $steps[$index]['state'] = 'active';
            } else {
                $steps[$index]['state'] = 'upcoming';
            }

            // Add progress count for testing stages
            if (isset($stageCounts[$step['key']]) && $totalSamples > 0) {
                $steps[$index]['progress'] = $stageCounts[$step['key']].'/'.$totalSamples;
            }
        }

        return $steps;
    }

    private function resolveStepperStage(TestRequest $testRequest, Collection $samples): string
    {
        if (in_array($testRequest->status, ['ready_for_delivery', 'completed'], true)) {
            return 'ready_for_delivery';
        }

        if ($samples->contains(fn (Sample $sample): bool => $sample->testProcesses->isEmpty())) {
            return 'preparation';
        }

        $processes = $samples->flatMap(function ($sample) {
            return $sample->testProcesses;
        });

        $stageChecks = [
            'interpretation',
            'instrumentation',
            'preparation',
        ];

        // First, check if any stage has an in-progress process (started but not completed)
        foreach ($stageChecks as $stage) {
            $hasInProgress = $processes->first(function ($process) use ($stage) {
                $value = $this->stageValue($process->stage ?? null);

                return $value === $stage && $process->started_at && ! $process->completed_at;
            });

            if ($hasInProgress) {
                return $stage;
            }
        }

        // No in-progress processes — determine next stage based on completed processes
        $preparationProcesses = $processes->filter(fn ($p) => $this->stageValue($p->stage ?? null) === 'preparation');
        $instrumentationProcesses = $processes->filter(fn ($p) => $this->stageValue($p->stage ?? null) === 'instrumentation');
        $interpretationProcesses = $processes->filter(fn ($p) => $this->stageValue($p->stage ?? null) === 'interpretation');

        if ($interpretationProcesses->isNotEmpty()) {
            $allInterpretationCompleted = $interpretationProcesses->every(fn ($p) => $p->completed_at);
            if ($allInterpretationCompleted) {
                return 'ready_for_delivery';
            }

            return 'interpretation';
        }

        if ($instrumentationProcesses->isNotEmpty()) {
            $allInstrumentationCompleted = $instrumentationProcesses->every(fn ($p) => $p->completed_at);

            if ($allInstrumentationCompleted) {
                return 'interpretation';
            }

            return 'instrumentation';
        }

        if ($preparationProcesses->isNotEmpty()) {
            $allPreparationCompleted = $preparationProcesses->every(fn ($p) => $p->completed_at);

            if ($allPreparationCompleted) {
                return 'instrumentation';
            }

            return 'preparation';
        }

        if ($testRequest->status === 'in_testing') {
            return 'preparation';
        }

        return 'submitted';
    }
}
