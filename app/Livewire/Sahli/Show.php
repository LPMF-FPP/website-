<?php

namespace App\Livewire\Sahli;

use App\Models\ExpertWitnessRequest;
use App\Services\ExpertWitnessService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public ExpertWitnessRequest $sahli;

    public string $sprinNumber = '';

    public string $sprinDate = '';

    public function mount(ExpertWitnessRequest $expertWitnessRequest): void
    {
        $this->sahli = $expertWitnessRequest->load(['milestones.completedBy', 'documents', 'testRequest']);
        $this->sprinNumber = (string) ($this->sahli->sprin_number ?? '');
        $this->sprinDate = $this->sahli->sprin_date?->format('Y-m-d') ?? '';
    }

    public function saveSprin(): void
    {
        $this->authorize('update', $this->sahli);
        $this->validate([
            'sprinNumber' => ['required', 'string', 'max:255'],
            'sprinDate' => ['required', 'date'],
        ]);

        $this->sahli->update([
            'sprin_number' => $this->sprinNumber,
            'sprin_date' => $this->sprinDate,
        ]);
        $this->sahli->refresh()->load(['milestones.completedBy', 'documents', 'testRequest']);
        session()->flash('success', 'Identitas sprin berhasil disimpan.');
    }

    public function toggleMilestone(string $code, ExpertWitnessService $service): void
    {
        $this->authorize('update', $this->sahli);
        $milestone = $this->sahli->milestones->firstWhere('code', $code);
        abort_unless($milestone, 404);

        $service->updateMilestone($this->sahli, $code, ! (bool) $milestone->completed_at, Auth::user());
        $this->sahli->refresh()->load(['milestones.completedBy', 'documents', 'testRequest']);
    }

    public function render(): View
    {
        $references = app(ExpertWitnessService::class)->farmapolReferences($this->sahli);

        return view('livewire.sahli.show', compact('references'));
    }
}
