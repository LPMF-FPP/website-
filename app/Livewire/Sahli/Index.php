<?php

namespace App\Livewire\Sahli;

use App\Models\ExpertWitnessRequest;
use App\Services\ExpertWitnessService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'open';

    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => 'open'],
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
                    $query->where('letter_number', 'like', $term)
                        ->orWhere('investigator_name', 'like', $term)
                        ->orWhere('investigator_institution', 'like', $term);
                });
            })
            ->when($this->status === 'open', fn ($query) => $query->whereNull('completed_at'))
            ->when($this->status === 'completed', fn ($query) => $query->whereNotNull('completed_at'))
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
