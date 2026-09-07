<?php

namespace App\Livewire\Sahli;

use App\Services\ExpertWitnessService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public $submissionLetter;

    public string $letterNumber = '';

    public string $letterDate = '';

    public string $investigatorName = '';

    public string $investigatorInstitution = '';

    public string $investigatorPhone = '';

    public string $notes = '';

    public string $submissionToken = '';

    public function mount(): void
    {
        $this->submissionToken = (string) Str::uuid();
    }

    public function save(ExpertWitnessService $service): void
    {
        $this->authorize('create', \App\Models\ExpertWitnessRequest::class);

        $data = $this->validate([
            'submissionLetter' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'letterNumber' => ['required', 'string', 'max:255'],
            'letterDate' => ['required', 'date'],
            'investigatorName' => ['required', 'string', 'min:3', 'max:255'],
            'investigatorInstitution' => ['required', 'string', 'max:255'],
            'investigatorPhone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'submissionToken' => ['required', 'uuid'],
        ]);

        $request = $service->createExternal([
            'letter_number' => $data['letterNumber'],
            'letter_date' => $data['letterDate'],
            'investigator_name' => $data['investigatorName'],
            'investigator_institution' => $data['investigatorInstitution'],
            'investigator_phone' => $data['investigatorPhone'],
            'notes' => $data['notes'] ?? null,
            'submission_token' => $data['submissionToken'],
        ], $this->submissionLetter, Auth::user());

        session()->flash('success', 'Pengajuan sahli berhasil disimpan.');
        $this->redirectRoute('sahli.show', $request, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.sahli.create');
    }
}
