<?php

namespace App\Http\Controllers;

use App\Enums\TestProcessStage;
use App\Models\RecentRequest;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcessController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $scope = $request->query('scope', 'all');

        $query = TestRequest::with(['investigator'])
            ->whereNotIn('status', ['ready_for_delivery', 'completed', 'rejected'])
            ->orderByDesc('created_at');

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $applyLike = function ($subQuery, string $column) use ($like) {
                $subQuery->whereRaw("LOWER({$column}) LIKE ?", [$like]);
            };

            $query->where(function ($subQuery) use ($applyLike, $like, $scope) {
                if ($scope === 'receipt_number') {
                    $applyLike($subQuery, 'receipt_number');

                    return;
                }

                if ($scope === 'request_number') {
                    $applyLike($subQuery, 'request_number');

                    return;
                }

                if ($scope === 'investigator') {
                    $subQuery->whereHas('investigator', function ($investigatorQuery) use ($applyLike, $like) {
                        $investigatorQuery
                            ->where(function ($investigatorSub) use ($applyLike, $like) {
                                $applyLike($investigatorSub, 'name');
                                $investigatorSub->orWhereRaw('LOWER(jurisdiction) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(institution) LIKE ?', [$like]);
                            });
                    });
                    $subQuery->orWhereHas('user', function ($userQuery) use ($applyLike) {
                        $applyLike($userQuery, 'name');
                    });

                    return;
                }

                $subQuery
                    ->where(function ($requestSub) use ($applyLike, $like) {
                        $applyLike($requestSub, 'receipt_number');
                        $requestSub->orWhereRaw('LOWER(request_number) LIKE ?', [$like]);
                    })
                    ->orWhereHas('investigator', function ($investigatorQuery) use ($applyLike, $like) {
                        $investigatorQuery
                            ->where(function ($investigatorSub) use ($applyLike, $like) {
                                $applyLike($investigatorSub, 'name');
                                $investigatorSub->orWhereRaw('LOWER(jurisdiction) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(institution) LIKE ?', [$like]);
                            });
                    })
                    ->orWhereHas('user', function ($userQuery) use ($applyLike) {
                        $applyLike($userQuery, 'name');
                    });
            });
        }

        $requests = $query->paginate(10)->withQueryString();

        $recentRequests = collect();
        if ($request->user()) {
            $recentRequests = RecentRequest::with(['request.investigator'])
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_opened_at')
                ->limit(5)
                ->get();
        }

        return view('process.index', [
            'requests' => $requests,
            'recentRequests' => $recentRequests,
            'filters' => [
                'q' => $search,
                'scope' => $scope,
            ],
        ]);
    }

    public function show(Request $request, TestRequest $testRequest): View
    {
        $testRequest->load(['investigator', 'samples']);

        $this->touchRecent($testRequest, $request->user());

        $filters = $request->only(['stage', 'short_description', 'status']);
        $stageFilter = $filters['stage'] ?? null;
        $shortDescriptionFilter = $filters['short_description'] ?? null;
        $statusFilter = $filters['status'] ?? null;

        $samplesQuery = Sample::with(['testProcesses'])
            ->where('test_request_id', $testRequest->id);

        if ($shortDescriptionFilter) {
            $samplesQuery->where('short_description', $shortDescriptionFilter);
        }

        if ($stageFilter) {
            $samplesQuery->whereHas('testProcesses', function ($processQuery) use ($stageFilter) {
                $processQuery->where('stage', $stageFilter);
            });
        }

        $samples = $samplesQuery->get();

        $rows = $this->mapSamplesWithProcessState($samples);

        if ($statusFilter) {
            $rows = $rows->where('current_status_key', $statusFilter);
        }

        $hasProcesses = $samples->flatMap(function ($sample) {
            return $sample->testProcesses;
        })->isNotEmpty();

        $paginatedSamples = $this->paginateCollection($rows, 10, $this->cleanQuery($filters));

        $currentStageKey = $this->resolveStepperStage($testRequest, $samples);

        // Check if all samples have completed interpretation - ready for delivery
        $readyForDelivery = false;
        if ($samples->isNotEmpty()) {
            $allProcesses = $samples->flatMap(fn ($s) => $s->testProcesses);
            $interpretationProcesses = $allProcesses->filter(
                fn ($p) => $this->stageValue($p->stage) === TestProcessStage::INTERPRETATION->value
            );

            // Ready for delivery if:
            // 1. There are interpretation processes
            // 2. All interpretation processes are completed
            // 3. TestRequest status is not already ready_for_delivery or completed
            if ($interpretationProcesses->isNotEmpty()) {
                $allInterpretationCompleted = $interpretationProcesses->every(fn ($p) => $p->completed_at);
                $readyForDelivery = $allInterpretationCompleted &&
                    ! in_array($testRequest->status, ['ready_for_delivery', 'completed'], true);
            }
        }

        return view('process.show', [
            'testRequest' => $testRequest,
            'samples' => $paginatedSamples,
            'filters' => $filters,
            'hasProcesses' => $hasProcesses,
            'stepper' => $this->buildStepper($currentStageKey),
            'stageOptions' => TestProcessStage::cases(),
            'shortDescriptions' => $testRequest->samples
                ->pluck('short_description')
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'statusOptions' => [
                'pending' => 'Pending',
                'in_progress' => 'Berjalan',
                'completed' => 'Selesai',
            ],
            'readyForDelivery' => $readyForDelivery,
        ]);
    }

    public function storeRecent(Request $request, TestRequest $testRequest): RedirectResponse
    {
        $this->touchRecent($testRequest, $request->user());

        return redirect()->route('testing.show', $testRequest);
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

    public function storeProcess(Request $request, TestRequest $testRequest): RedirectResponse
    {
        $validated = $request->validate([
            'sample_id' => ['required', 'exists:samples,id'],
            'stage' => ['required', Rule::in(array_map(fn ($stage) => $stage->value, TestProcessStage::cases()))],
            'scheduled_at' => ['nullable', 'date'],
            'performed_by' => ['nullable', 'exists:users,id'],
        ]);

        $sample = Sample::where('id', $validated['sample_id'])
            ->where('test_request_id', $testRequest->id)
            ->firstOrFail();

        $exists = SampleTestProcess::where('sample_id', $sample->id)
            ->where('stage', $validated['stage'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'stage' => 'Proses untuk tahapan ini sudah ada pada sampel tersebut.',
            ]);
        }

        $metadata = [];
        if (! empty($validated['scheduled_at'])) {
            $metadata['scheduled_at'] = $validated['scheduled_at'];
        }

        SampleTestProcess::create([
            'sample_id' => $sample->id,
            'stage' => $validated['stage'],
            'performed_by' => $validated['performed_by'] ?? null,
            'metadata' => ! empty($metadata) ? $metadata : null,
        ]);

        return redirect()
            ->route('testing.show', $testRequest)
            ->with('success', 'Proses berhasil ditambahkan.');
    }

    private function buildStepper(string $currentStageKey): array
    {
        $steps = [
            ['key' => 'submitted', 'label' => 'SUBMITTED'],
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

        // No in-progress processes - determine next stage based on completed processes
        // Group processes by stage
        $preparationProcesses = $processes->filter(fn ($p) => $this->stageValue($p->stage ?? null) === 'preparation');
        $instrumentationProcesses = $processes->filter(fn ($p) => $this->stageValue($p->stage ?? null) === 'instrumentation');
        $interpretationProcesses = $processes->filter(fn ($p) => $this->stageValue($p->stage ?? null) === 'interpretation');

        // Check if interpretation exists
        if ($interpretationProcesses->isNotEmpty()) {
            $allInterpretationCompleted = $interpretationProcesses->every(fn ($p) => $p->completed_at);
            if ($allInterpretationCompleted) {
                return 'ready_for_delivery';
            }

            return 'interpretation';
        }

        // Check instrumentation - if any exists and all are completed, advance to interpretation
        if ($instrumentationProcesses->isNotEmpty()) {
            $allInstrumentationCompleted = $instrumentationProcesses->every(fn ($p) => $p->completed_at);

            if ($allInstrumentationCompleted) {
                // All existing instrumentation processes are done, advance to interpretation
                return 'interpretation';
            }

            return 'instrumentation';
        }

        // Check preparation - if any exists and all are completed, advance to instrumentation
        if ($preparationProcesses->isNotEmpty()) {
            $allPreparationCompleted = $preparationProcesses->every(fn ($p) => $p->completed_at);

            if ($allPreparationCompleted) {
                // All existing preparation processes are done, advance to instrumentation
                return 'instrumentation';
            }

            return 'preparation';
        }

        if ($testRequest->status === 'in_testing') {
            return 'preparation';
        }

        return 'submitted';
    }

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

        return $samples->map(function ($sample) use ($stageOrder, $stageSequence) {
            $processes = $sample->testProcesses
                ->sortBy(function ($process) use ($stageOrder) {
                    $stage = $this->stageValue($process->stage ?? null);

                    return $stageOrder[$stage] ?? 99;
                })
                ->values();

            // First, check for any in-progress process
            $currentProcess = $processes->first(function ($process) {
                return $process->started_at && ! $process->completed_at;
            });

            $statusKey = 'pending';
            $statusLabel = 'Belum dimulai';
            $nextStageLabel = null;

            if ($currentProcess) {
                // There's an in-progress process
                $statusKey = 'in_progress';
                $statusLabel = 'Berjalan';
            } else {
                // No in-progress process - determine next stage based on completed processes
                $completedStages = $processes
                    ->filter(fn ($p) => $p->completed_at)
                    ->map(fn ($p) => $this->stageValue($p->stage))
                    ->unique()
                    ->values()
                    ->toArray();

                // Find the highest completed stage
                $highestCompletedIndex = -1;
                foreach ($stageSequence as $index => $stage) {
                    if (in_array($stage, $completedStages, true)) {
                        $highestCompletedIndex = $index;
                    }
                }

                // Determine next stage
                $nextStageIndex = $highestCompletedIndex + 1;

                if ($nextStageIndex < count($stageSequence)) {
                    // There's a next stage to do
                    $nextStageValue = $stageSequence[$nextStageIndex];

                    // Check if there's already a process for the next stage
                    $nextStageProcess = $processes->first(function ($p) use ($nextStageValue) {
                        return $this->stageValue($p->stage) === $nextStageValue;
                    });

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
                        // No process exists for next stage yet - show the next expected stage
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

    private function paginateCollection(Collection $items, int $perPage, array $query): LengthAwarePaginator
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

    private function cleanQuery(array $query): array
    {
        return array_filter($query, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function stageValue($stage): ?string
    {
        if ($stage instanceof TestProcessStage) {
            return $stage->value;
        }

        return $stage ? (string) $stage : null;
    }

    private function touchRecent(TestRequest $testRequest, $user): void
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
}
