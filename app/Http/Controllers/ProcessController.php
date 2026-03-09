<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesProcessStage;
use App\Models\Sample;
use App\Models\TestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function show(Request $request, TestRequest $testRequest): View
    {
        $testRequest->load([
            'investigator',
            'samples',
            'evidenceUnits.remainingUnits',
        ]);

        $this->touchRecentRequest($testRequest, $request->user());

        // Database-level pagination instead of in-memory
        $paginatedRaw = Sample::with(['testProcesses'])
            ->where('test_request_id', $testRequest->id)
            ->orderBy('id')
            ->paginate(10);

        // Map process state onto the paginated items
        $rows = $this->mapSamplesWithProcessState($paginatedRaw->getCollection());
        $paginatedSamples = $paginatedRaw->setCollection($rows);

        // Use all samples (not just current page) for stepper & readiness checks
        $allSamples = $testRequest->samples->load('testProcesses');

        $hasProcesses = $allSamples->flatMap(function ($sample) {
            return $sample->testProcesses;
        })->isNotEmpty();

        $currentStageKey = $this->resolveStepperStage($testRequest, $allSamples);

        $readyForDelivery = $this->isReadyForDelivery($testRequest, $allSamples);

        return view('process.show', [
            'testRequest' => $testRequest,
            'samples' => $paginatedSamples,
            'hasProcesses' => $hasProcesses,
            'stepper' => $this->buildStepper($currentStageKey, $allSamples),
            'readyForDelivery' => $readyForDelivery,
        ]);
    }

    public function markReadyForDelivery(TestRequest $testRequest, \App\Services\WhatsApp\NotificationService $notificationService): RedirectResponse
    {
        $requiredStages = ['preparation', 'instrumentation', 'interpretation'];

        $samples = $testRequest->samples()
            ->select('id', 'sample_code', 'test_request_id')
            ->with(['testProcesses' => function ($q) {
                $q->select('id', 'sample_id', 'stage', 'completed_at');
            }])
            ->get();

        $incompleteSamples = [];
        foreach ($samples as $sample) {
            $completedStages = $sample->testProcesses
                ->filter(fn ($p) => $p->completed_at !== null)
                ->map(fn ($p) => $this->stageValue($p->stage))
                ->filter(fn ($stage) => $stage !== null && in_array($stage, $requiredStages, true))
                ->unique()
                ->values()
                ->toArray();

            $missingStages = array_values(array_diff($requiredStages, $completedStages));

            if (! empty($missingStages)) {
                $label = $sample->sample_code ?: 'Sampel ID:'.$sample->id;
                $incompleteSamples[] = $label.' (belum: '.implode(', ', $missingStages).')';
            }
        }

        if (! empty($incompleteSamples)) {
            return back()->withErrors([
                'error' => 'Tidak dapat mengirim ke penyerahan. Sampel berikut belum lengkap: '.implode('; ', $incompleteSamples),
            ]);
        }

        // Update test request status to ready_for_delivery
        $testRequest->update([
            'status' => 'ready_for_delivery',
            'ready_for_delivery_at' => now(),
        ]);

        // Also update all samples status
        $testRequest->samples()->update([
            'status' => 'ready_for_delivery',
            'sample_status' => 'ready_for_delivery',
        ]);

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
