<?php

namespace App\Livewire\Sahli;

use App\Models\ExpertWitnessRequest;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Edit extends Component
{
    public ExpertWitnessRequest $sahli;

    public string $letterNumber = '';

    public string $letterDate = '';

    public string $suspectName = '';

    public string $investigatorName = '';

    public string $investigatorRank = '';

    public string $investigatorInstitution = '';

    public string $investigatorPhone = '';

    public string $notes = '';

    public function mount(ExpertWitnessRequest $expertWitnessRequest): void
    {
        $this->sahli = $expertWitnessRequest->load(['milestones', 'documents', 'investigator', 'testRequest.investigator', 'testRequest.suspects']);
        $this->letterNumber = (string) ($this->sahli->letter_number ?? '');
        $this->letterDate = $this->sahli->letter_date?->format('Y-m-d') ?? '';
        $this->suspectName = (string) ($this->sahli->suspect_name
            ?: $this->sahli->testRequest?->display_suspect_names[0]
            ?: '');
        $this->investigatorName = (string) ($this->sahli->investigator_name ?? '');
        $this->investigatorRank = (string) ($this->sahli->investigator_rank
            ?: $this->sahli->investigator?->rank
            ?: $this->sahli->testRequest?->investigator?->rank
            ?: '');
        $this->investigatorInstitution = (string) ($this->sahli->investigator_institution ?? '');
        $this->investigatorPhone = (string) ($this->sahli->investigator_phone ?? '');
        $this->notes = (string) ($this->sahli->notes ?? '');
    }

    public function save(): void
    {
        $this->authorize('update', $this->sahli);

        $data = $this->validate([
            'letterNumber' => ['required', 'string', 'max:255'],
            'letterDate' => ['required', 'date'],
            'suspectName' => ['required', 'string', 'min:2', 'max:255'],
            'investigatorName' => ['required', 'string', 'min:3', 'max:255'],
            'investigatorRank' => ['required', 'string', 'max:100'],
            'investigatorInstitution' => ['required', 'string', 'max:255'],
            'investigatorPhone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->sahli->update([
            'letter_number' => $data['letterNumber'],
            'letter_date' => $data['letterDate'],
            'suspect_name' => $data['suspectName'],
            'investigator_name' => $data['investigatorName'],
            'investigator_rank' => $data['investigatorRank'],
            'investigator_institution' => $data['investigatorInstitution'],
            'investigator_phone' => $data['investigatorPhone'],
            'notes' => $data['notes'] ?? null,
        ]);

        session()->flash('success', 'Data pengajuan Sahli berhasil diperbarui.');
        $this->redirectRoute('sahli.show', $this->sahli, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.sahli.edit');
    }
}
