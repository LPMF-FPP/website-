<?php

namespace App\Livewire\Pengujian;

use App\Concerns\ResolvesProcessStage;
use App\Enums\TestProcessStage;
use App\Models\Sample;
use App\Models\TestRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Workbench extends Component
{
    use ResolvesProcessStage;
    use WithPagination;

    public string $q = '';

    public string $scope = 'all';

    public ?int $selectedRequestId = null;

    public ?string $sampleStage = null;

    public ?string $sampleShortDescription = null;

    public ?string $sampleStatus = null;

    public ?string $detailError = null;

    public string $sortField = 'receipt_number';

    public string $sortDirection = 'asc';

    protected array $queryString = [
        'q' => ['except' => ''],
        'scope' => ['except' => 'all'],
        'sortField' => ['as' => 'sort', 'except' => 'receipt_number'],
        'sortDirection' => ['as' => 'direction', 'except' => 'asc'],
        'selectedRequestId' => ['as' => 'selected', 'except' => null],
    ];

    public function mount(): void
    {
        if (! in_array($this->scope, ['all', 'receipt_number', 'request_number', 'investigator'], true)) {
            $this->scope = 'all';
        }

        if (! in_array($this->sortField, ['receipt_number', 'created_at'], true)) {
            $this->sortField = 'receipt_number';
        }

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'asc';
        }
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingScope(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['receipt_number', 'created_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'receipt_number' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function selectRequest(int $requestId): void
    {
        $this->selectedRequestId = $requestId;
        $this->detailError = null;
        $this->sampleStage = null;
        $this->sampleShortDescription = null;
        $this->sampleStatus = null;

        $request = TestRequest::query()->find($requestId);
        if ($request && Auth::check()) {
            $this->touchRecentRequest($request, Auth::user());
        }
    }

    public function closeRequest(): void
    {
        $this->selectedRequestId = null;
        $this->detailError = null;
    }

    public function render(): View
    {
        $requests = $this->buildRequestsQuery()->paginate(10);

        $selectedRequest = null;
        $sampleRows = collect();
        $shortDescriptions = collect();
        $readyForDelivery = false;
        $this->detailError = null;

        if ($this->selectedRequestId) {
            try {
                $selectedRequest = TestRequest::query()
                    ->with(['investigator'])
                    ->withCount('samples')
                    ->find($this->selectedRequestId);

                if ($selectedRequest) {
                    // Single query: fetch ALL samples with processes (for descriptions + readyForDelivery)
                    $allSamples = Sample::query()
                        ->with('testProcesses')
                        ->where('test_request_id', $selectedRequest->id)
                        ->get();

                    $shortDescriptions = $allSamples
                        ->pluck('short_description')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();

                    $readyForDelivery = $this->isReadyForDelivery($selectedRequest, $allSamples);

                    // Apply filters in-memory (data is already loaded)
                    $filteredSamples = $allSamples;

                    if ($this->sampleShortDescription) {
                        $filteredSamples = $filteredSamples->where('short_description', $this->sampleShortDescription);
                    }

                    if ($this->sampleStage) {
                        $filteredSamples = $filteredSamples->filter(function (Sample $sample) {
                            return $sample->testProcesses->contains(fn ($p) => (string) ($p->stage instanceof TestProcessStage ? $p->stage->value : $p->stage) === $this->sampleStage);
                        });
                    }

                    $sampleRows = $this->mapSamplesWithProcessState($filteredSamples->values());

                    if ($this->sampleStatus) {
                        $sampleRows = $sampleRows->where('current_status_key', $this->sampleStatus)->values();
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
                $selectedRequest = null;
                $this->detailError = 'Gagal memuat detail resi. Silakan coba lagi.';
            }
        }

        // Summary stats — uses same filter as the table query (excludes ready_for_delivery)
        $summaryStats = TestRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereNotIn('status', ['ready_for_delivery', 'completed', 'rejected'])
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('livewire.pengujian.workbench', [
            'requests' => $requests,
            'selectedRequest' => $selectedRequest,
            'samples' => $sampleRows->take(10),
            'samplesTotal' => $sampleRows->count(),
            'shortDescriptions' => $shortDescriptions,
            'stageOptions' => TestProcessStage::cases(),
            'statusOptions' => [
                'pending' => 'Pending',
                'in_progress' => 'Berjalan',
                'completed' => 'Selesai',
            ],
            'readyForDelivery' => $readyForDelivery,
            'summaryStats' => $summaryStats,
        ]);
    }

    private function buildRequestsQuery(): Builder
    {
        $search = trim($this->q);

        $query = TestRequest::query()
            ->with(['investigator'])
            ->withCount('samples')
            ->whereNotIn('status', ['ready_for_delivery', 'completed', 'rejected']);

        if ($this->sortField === 'receipt_number') {
            $query->orderByRaw("CASE WHEN COALESCE(receipt_number, request_number) ~ '\\d+$' THEN CAST(substring(COALESCE(receipt_number, request_number) from '(\\d+)$') AS BIGINT) END {$this->sortDirection} nulls last")
                ->orderByRaw('COALESCE(receipt_number, request_number) '.$this->sortDirection)
                ->orderBy('created_at');
        } else {
            $query->orderBy('created_at', $this->sortDirection)
                ->orderByRaw('COALESCE(receipt_number, request_number) asc');
        }

        if ($search === '') {
            return $query;
        }

        $like = '%'.strtolower($search).'%';
        $applyLike = function (Builder $subQuery, string $column) use ($like): void {
            $subQuery->whereRaw("LOWER({$column}) LIKE ?", [$like]);
        };

        $query->where(function (Builder $subQuery) use ($applyLike, $like): void {
            if ($this->scope === 'receipt_number') {
                $applyLike($subQuery, 'receipt_number');

                return;
            }

            if ($this->scope === 'request_number') {
                $applyLike($subQuery, 'request_number');

                return;
            }

            if ($this->scope === 'investigator') {
                $subQuery->whereHas('investigator', function (Builder $investigatorQuery) use ($applyLike, $like): void {
                    $investigatorQuery->where(function (Builder $investigatorSub) use ($applyLike, $like): void {
                        $applyLike($investigatorSub, 'name');
                        $investigatorSub->orWhereRaw('LOWER(jurisdiction) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(institution) LIKE ?', [$like]);
                    });
                });

                $subQuery->orWhereHas('user', function (Builder $userQuery) use ($applyLike): void {
                    $applyLike($userQuery, 'name');
                });

                return;
            }

            $subQuery
                ->where(function (Builder $requestSub) use ($applyLike, $like): void {
                    $applyLike($requestSub, 'receipt_number');
                    $requestSub->orWhereRaw('LOWER(request_number) LIKE ?', [$like]);
                })
                ->orWhereHas('investigator', function (Builder $investigatorQuery) use ($applyLike, $like): void {
                    $investigatorQuery->where(function (Builder $investigatorSub) use ($applyLike, $like): void {
                        $applyLike($investigatorSub, 'name');
                        $investigatorSub->orWhereRaw('LOWER(jurisdiction) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(institution) LIKE ?', [$like]);
                    });
                })
                ->orWhereHas('user', function (Builder $userQuery) use ($applyLike): void {
                    $applyLike($userQuery, 'name');
                });
        });

        return $query;
    }
}
