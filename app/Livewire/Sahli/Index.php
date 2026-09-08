<?php

namespace App\Livewire\Sahli;

use App\Models\ExpertWitnessRequest;
use App\Services\ExpertWitnessService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'open';

    public string $month = '';

    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => 'open'],
        'month' => ['except' => ''],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(ExpertWitnessService $service): void
    {
        if (! in_array($this->status, ['open', 'completed', 'all'], true)) {
            $this->status = 'open';
        }

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'desc';
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = '';
        }

        $service->syncFarmapolRequests();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingMonth(): void
    {
        $this->resetPage();
    }

    public function sortByDate(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->resetPage();
    }

    public function render(): View
    {
        $summary = [
            'open' => ExpertWitnessRequest::query()->whereNull('completed_at')->count(),
            'completed' => ExpertWitnessRequest::query()->whereNotNull('completed_at')->count(),
            'farmapol' => ExpertWitnessRequest::query()->where('source', ExpertWitnessRequest::SOURCE_FARMAPOL)->count(),
            'external' => ExpertWitnessRequest::query()->where('source', ExpertWitnessRequest::SOURCE_EXTERNAL)->count(),
        ];

        $query = ExpertWitnessRequest::query()
            ->with(['milestones', 'testRequest.suspects'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.addcslashes($this->search, '%_').'%';
                $query->where(function ($query) use ($term): void {
                    $query->whereRaw('LOWER(letter_number) LIKE LOWER(?)', [$term])
                        ->orWhereRaw('LOWER(investigator_name) LIKE LOWER(?)', [$term])
                        ->orWhereRaw('LOWER(investigator_institution) LIKE LOWER(?)', [$term])
                        ->orWhereHas('testRequest', function ($query) use ($term): void {
                            $query->whereRaw('LOWER(suspect_name) LIKE LOWER(?)', [$term])
                                ->orWhereHas('suspects', fn ($query) => $query->whereRaw('LOWER(name) LIKE LOWER(?)', [$term]));
                        });
                });
            })
            ->when($this->status === 'open', fn ($query) => $query->whereNull('completed_at'))
            ->when($this->status === 'completed', fn ($query) => $query->whereNotNull('completed_at'))
            ->when($this->month !== '', function ($query): void {
                $start = CarbonImmutable::createFromFormat('!Y-m', $this->month)->startOfMonth();
                $end = $start->endOfMonth();

                $query->where(function ($query) use ($start, $end): void {
                    $query
                        ->where(function ($query) use ($start, $end): void {
                            $query->where('source', ExpertWitnessRequest::SOURCE_FARMAPOL)
                                ->whereHas('testRequest', fn ($query) => $query->whereBetween('created_at', [$start, $end]));
                        })
                        ->orWhere(function ($query) use ($start, $end): void {
                            $query->where('source', ExpertWitnessRequest::SOURCE_EXTERNAL)
                                ->whereBetween('submitted_at', [$start, $end]);
                        });
                });
            })
            ->orderByRaw($this->dateOrderSql().' '.$this->sortDirection)
            ->orderByDesc('id');

        return view('livewire.sahli.index', [
            'requests' => $query->paginate(12),
            'summary' => $summary,
        ]);
    }

    private function dateOrderSql(): string
    {
        return "CASE WHEN source = 'farmapol' THEN COALESCE((SELECT created_at FROM test_requests WHERE test_requests.id = expert_witness_requests.test_request_id), submitted_at) ELSE submitted_at END";
    }
}
