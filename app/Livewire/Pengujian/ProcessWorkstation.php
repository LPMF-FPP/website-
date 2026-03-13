<?php

namespace App\Livewire\Pengujian;

use App\Enums\TestProcessStage;
use App\Models\AuditTrail;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\User;
use App\Services\ActiveSubstanceService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProcessWorkstation extends Component
{
    use WithFileUploads;

    public ?SampleTestProcess $process = null;

    public ?Sample $sample = null;

    // UI State
    public bool $isOpen = false;

    // Core Data
    public array $actionState = [];

    public $recentWorkflowEvents = [];

    public $interpretationDetails = null;

    // Form fields
    public $sample_id;

    public $stage;

    public $performed_by;

    public $started_at;

    public $completed_at;

    public $notes;

    public $metadata_raw;

    // Interpretation form fields
    public $instrument;

    public $test_result;

    public $detected_substance;

    public $test_result_file;

    // Secondary Interpretation fields
    public $instrument_2;

    public $test_result_2;

    public $detected_substance_2;

    public $test_result_file_2;

    public ?string $unlockReason = null;

    // View Options
    public $analysts = [];

    public $stages = [];

    public $activeSubstances = [];

    public $instrumentOptions = [];

    public $suggestedInstrument = null;

    public function mount()
    {
        $this->isOpen = false;
    }

    #[On('open-workstation')]
    public function openPanel($processId = null)
    {
        if (! $processId) {
            return;
        }

        $this->loadProcess($processId);
        $this->isOpen = true;
        $this->dispatch('workstation-opened');
    }

    public function closePanel()
    {
        $this->isOpen = false;
        $this->process = null;
        $this->sample = null;
        $this->unlockReason = null;
        $this->dispatch('workstation-closed');
    }

    public function loadProcess($processId)
    {
        $this->process = SampleTestProcess::with(['sample.testRequest.investigator', 'sample.testProcesses', 'analyst'])->findOrFail($processId);
        $this->sample = $this->process->sample;

        $workflowService = app(WorkflowService::class);
        $activeSubstanceService = app(ActiveSubstanceService::class);

        // Action States
        $startAction = $workflowService->canStartProcess($this->process);
        $completeAction = $workflowService->canCompleteProcess($this->process);
        $unlockAction = $workflowService->canUnlockProcess($this->process);

        $this->actionState = [
            'can_start' => $startAction['allowed'],
            'start_reason' => $startAction['reason'],
            'can_complete' => $completeAction['allowed'],
            'complete_reason' => $completeAction['reason'],
            'can_unlock' => $unlockAction['allowed'],
            'unlock_reason' => $unlockAction['reason'],
        ];

        // Audit Trail
        $recentEvents = AuditTrail::query()
            ->where('table_name', 'sample_test_processes')
            ->where('record_id', (string) $this->process->id)
            ->whereIn('action', ['process_started', 'process_completed', 'process_unlocked'])
            ->orderByDesc('changed_at')
            ->limit(8)
            ->get();

        $actorIds = $recentEvents->pluck('changed_by')->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values();
        $actorNames = User::whereIn('id', $actorIds)->pluck('name', 'id');

        $this->recentWorkflowEvents = $recentEvents->map(function ($event) use ($actorNames) {
            $event->actor_name = is_numeric($event->changed_by) ? ($actorNames[(int) $event->changed_by] ?? 'Pengguna') : 'Sistem';

            return $event;
        });

        $staffRoles = app(\App\Support\RoleCatalog::class)->staffRoles();
        $this->analysts = User::where('is_active', true)->whereIn('role', $staffRoles)->orderBy('name')->get();

        $this->stages = collect(TestProcessStage::cases())
            ->reject(fn ($stage) => $stage === TestProcessStage::ADMINISTRATION)
            ->mapWithKeys(fn ($stage) => [$stage->value => $stage->label()])
            ->toArray();

        // Bind Base Form State
        $this->sample_id = $this->process->sample_id;
        $this->stage = $this->process->stage instanceof TestProcessStage ? $this->process->stage->value : $this->process->stage;
        $this->performed_by = $this->process->performed_by;
        $this->started_at = $this->process->started_at?->format('Y-m-d\TH:i');
        $this->completed_at = $this->process->completed_at?->format('Y-m-d\TH:i');
        $this->notes = $this->process->notes;
        $this->metadata_raw = $this->process->metadata ? json_encode($this->process->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';

        // Interpretation Logic
        $metadata = $this->process->metadata ?? [];
        $activeSubstancesData = $activeSubstanceService->breakdown(0);
        $this->activeSubstances = collect($activeSubstancesData['labels'] ?? [])->filter()->values();

        $currentDetectedSubstance = $metadata['detected_substance'] ?? $metadata['detection'] ?? $metadata['hasil'] ?? $this->sample?->active_substance ?? null;
        if ($currentDetectedSubstance && ! $this->activeSubstances->contains($currentDetectedSubstance)) {
            $this->activeSubstances->prepend($currentDetectedSubstance);
        }

        $this->instrumentOptions = [
            'UV-VIS Spectrophotometer' => 'UV-VIS Spectrophotometer',
            'GC-MS (Gas Chromatography-Mass Spectrometry)' => 'GC-MS (Gas Chromatography-Mass Spectrometry)',
            'LC-MS (Liquid Chromatography-Mass Spectrometry)' => 'LC-MS (Liquid Chromatography-Mass Spectrometry)',
        ];

        $methodToInstrumentMap = [
            'uv_vis' => 'UV-VIS Spectrophotometer',
            'gc_ms' => 'GC-MS (Gas Chromatography-Mass Spectrometry)',
            'lc_ms' => 'LC-MS (Liquid Chromatography-Mass Spectrometry)',
        ];

        $sampleTestMethods = $this->sample->test_methods ?? [];
        if (is_string($sampleTestMethods)) {
            $sampleTestMethods = json_decode($sampleTestMethods, true) ?? [];
        }
        foreach ($sampleTestMethods as $method) {
            if (isset($methodToInstrumentMap[$method])) {
                $this->suggestedInstrument = $methodToInstrumentMap[$method];
                break;
            }
        }

        $this->instrument = $metadata['instrument'] ?? $metadata['instrument_pengujian'] ?? null;
        $this->test_result = $metadata['test_result'] ?? null;
        $this->detected_substance = $currentDetectedSubstance;

        $multi = (isset($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) ? $metadata['multi_interpretations'] : [];
        $secondary = is_array($multi) && count($multi) >= 1 && is_array($multi[0]) ? $multi[0] : [];
        $this->instrument_2 = $secondary['instrument'] ?? null;
        $this->test_result_2 = $secondary['test_result'] ?? null;
        $this->detected_substance_2 = $secondary['detected_substance'] ?? null;

        // Visual Presentation state for Interpretation
        if ($this->stage === TestProcessStage::INTERPRETATION->value) {
            $resultRaw = $this->test_result;
            $resultLabel = match ($resultRaw) {
                'positive' => 'Positif',
                'negative' => 'Negatif',
                default => 'Belum ditentukan',
            };

            $reportNumber = $metadata['lhu_number'] ?? $this->process->report_number ?? $this->process->lab_report_no ?? $metadata['report_number'] ?? '-';
            $reportDoc = \App\Models\Document::query()
                ->where('test_request_id', $this->sample->test_request_id)
                ->where('sample_id', $this->process->sample_id)
                ->whereIn('document_type', ['laporan_hasil_uji', 'lab_report'])
                ->latest()
                ->first();

            $attachmentPath = $metadata['test_result_attachment_path'] ?? null;
            $attachmentOriginal = $metadata['test_result_attachment_original'] ?? null;
            $attachmentUrl = $attachmentPath && Storage::disk('public')->exists($attachmentPath)
                ? asset('storage/'.ltrim($attachmentPath, '/'))
                : null;

            $multiInterpretations = [];
            if (! empty($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) {
                foreach ($metadata['multi_interpretations'] as $mi) {
                    if (! is_array($mi)) {
                        continue;
                    }
                    $raw = $mi['test_result'] ?? null;
                    $label = match ($raw) {
                        'positive' => 'Positif', 'negative' => 'Negatif', default => 'Belum ditentukan'
                    };
                    $path = $mi['test_result_attachment_path'] ?? null;
                    $url = $path && Storage::disk('public')->exists($path) ? asset('storage/'.ltrim($path, '/')) : null;
                    $multiInterpretations[] = [
                        'instrument' => $mi['instrument'] ?? null,
                        'detected_substance' => $mi['detected_substance'] ?? null,
                        'test_result' => $label,
                        'test_result_raw' => $raw,
                        'attachment_url' => $url,
                        'attachment_original' => $mi['test_result_attachment_original'] ?? null,
                    ];
                }
            }

            $this->interpretationDetails = [
                'instrument' => $this->instrument ?: 'Belum ditentukan',
                'detected_substance' => $this->detected_substance ?: 'Tidak ada hasil terdeteksi',
                'test_result' => $resultLabel,
                'test_result_raw' => $resultRaw,
                'report_number' => $reportNumber,
                'report_document' => $reportDoc,
                'report_exists' => $reportDoc !== null,
                'attachment_path' => $attachmentPath,
                'attachment_original' => $attachmentOriginal,
                'attachment_url' => $attachmentUrl,
                'multi' => $multiInterpretations,
            ];
        } else {
            $this->interpretationDetails = null;
        }

        // Emit an event to tell Alpine components that process data has changed so they re-initialize
        $this->dispatch('workstation-loaded', ['processId' => $this->process->id, 'sampleId' => $this->sample->id]);
    }

    public function render()
    {
        return view('livewire.pengujian.process-workstation');
    }

    public function startProcess()
    {
        if (! $this->process) {
            return;
        }

        $workflowService = app(WorkflowService::class);

        $state = $workflowService->canStartProcess($this->process);
        if (! $state['allowed']) {
            $this->dispatch('notify', title: 'Gagal', message: $state['reason'], type: 'error');

            return;
        }

        try {
            $workflowService->startExistingProcess($this->process);
        } catch (\Exception $e) {
            $this->dispatch('notify', title: 'Gagal', message: $e->getMessage(), type: 'error');

            return;
        }

        app(\App\Services\AuditTrailService::class)->logAction(
            table: 'sample_test_processes',
            recordId: $this->process->id,
            action: 'process_started',
            oldData: [],
            newData: ['started_at' => $this->process->started_at],
            notes: 'Tahapan proses mulai dikerjakan'
        );

        $this->loadProcess($this->process->id);
        $this->dispatch('notify', title: 'Berhasil', message: 'Proses berhasil dimulai.', type: 'success');
        $this->dispatch('sample-process-updated');
    }

    public function completeProcess()
    {
        if (! $this->process) {
            return;
        }

        $workflowService = app(WorkflowService::class);

        $state = $workflowService->canCompleteProcess($this->process);
        if (! $state['allowed']) {
            $this->dispatch('notify', title: 'Gagal', message: $state['reason'], type: 'error');

            return;
        }

        try {
            $nextProcess = $workflowService->completeTestProcess($this->process);
        } catch (\Exception $e) {
            $this->dispatch('notify', title: 'Gagal', message: $e->getMessage(), type: 'error');

            return;
        }

        app(\App\Services\AuditTrailService::class)->logAction(
            table: 'sample_test_processes',
            recordId: $this->process->id,
            action: 'process_completed',
            oldData: [],
            newData: ['completed_at' => $this->process->completed_at],
            notes: 'Tahapan proses telah diselesaikan'
        );

        // If a next stage was created, load it so the workstation shows the new stage
        if ($nextProcess) {
            $this->loadProcess($nextProcess->id);
            $this->dispatch('notify', title: 'Berhasil', message: 'Proses selesai. Tahap berikutnya telah disiapkan.', type: 'success');
        } else {
            $this->loadProcess($this->process->id);
            $this->dispatch('notify', title: 'Berhasil', message: 'Proses berhasil diselesaikan.', type: 'success');
        }
        $this->dispatch('sample-process-updated');
    }

    public function revertProcess()
    {
        if (! $this->process) {
            return;
        }

        $validated = $this->validate([
            'unlockReason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $workflowService = app(WorkflowService::class);

        $state = $workflowService->canUnlockProcess($this->process);
        if (! $state['allowed']) {
            $this->dispatch('notify', title: 'Gagal', message: $state['reason'], type: 'error');

            return;
        }

        try {
            $workflowService->unlockCompletedProcess($this->process, $validated['unlockReason']);
        } catch (\Exception $e) {
            $this->dispatch('notify', title: 'Gagal', message: $e->getMessage(), type: 'error');

            return;
        }

        app(\App\Services\AuditTrailService::class)->logAction(
            table: 'sample_test_processes',
            recordId: $this->process->id,
            action: 'process_unlocked',
            oldData: [],
            newData: ['completed_at' => null],
            notes: 'Penyelesaian tahap dibatalkan (dikembalikan ke status berjalan)'
        );

        $this->loadProcess($this->process->id);
        $this->unlockReason = null;
        $this->dispatch('notify', title: 'Berhasil', message: 'Proses berhasil dikembalikan.', type: 'success');
        $this->dispatch('sample-process-updated');
    }

    public function save()
    {
        $lhuGenerationFailed = false;
        $lhuGenerationMessage = null;

        $this->validate([
            'sample_id' => ['required', 'exists:samples,id'],
            'stage' => ['required', 'string', \Illuminate\Validation\Rule::in(array_column(TestProcessStage::cases(), 'value'))],
            'performed_by' => ['nullable', 'exists:users,id'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string'],
            'metadata_raw' => ['nullable', 'string'],
            'instrument' => ['nullable', 'string', 'max:255'],
            'test_result' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['positive', 'negative'])],
            'detected_substance' => ['nullable', 'string', 'max:255'],
            'test_result_file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
            'instrument_2' => ['nullable', 'string', 'max:255'],
            'test_result_2' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['positive', 'negative'])],
            'detected_substance_2' => ['nullable', 'string', 'max:255'],
            'test_result_file_2' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $exists = SampleTestProcess::where('sample_id', $this->sample_id)
            ->where('stage', $this->stage)
            ->where('id', '!=', $this->process->id)
            ->exists();

        if ($exists) {
            $this->addError('stage', 'Kombinasi sampel dan tahapan ini sudah ada.');

            return;
        }

        $metadata = $this->process->metadata ?? [];

        if ($this->metadata_raw !== null) {
            $trimmed = trim($this->metadata_raw);
            if ($trimmed === '') {
                $metadata = [];
            } else {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->addError('metadata_raw', 'Format JSON tidak valid.');

                    return;
                }
                $metadata = array_merge($metadata, $decoded);
            }
        }

        $isInterpretationStage = $this->stage === TestProcessStage::INTERPRETATION->value;

        if ($isInterpretationStage) {
            if ($this->instrument) {
                $metadata['instrument'] = $this->instrument;
            } elseif (array_key_exists('instrument', $metadata)) {
                unset($metadata['instrument']);
            }

            if ($this->test_result) {
                $metadata['test_result'] = $this->test_result;
            } elseif (array_key_exists('test_result', $metadata)) {
                unset($metadata['test_result']);
            }

            if ($this->detected_substance) {
                $metadata['detected_substance'] = $this->detected_substance;
            } elseif (array_key_exists('detected_substance', $metadata)) {
                unset($metadata['detected_substance']);
            }

            if ($this->test_result_file) {
                if (! empty($metadata['test_result_attachment_path']) && Storage::disk('public')->exists($metadata['test_result_attachment_path'])) {
                    Storage::disk('public')->delete($metadata['test_result_attachment_path']);
                }
                $ext = $this->test_result_file->getClientOriginalExtension();
                $storedPath = $this->test_result_file->storeAs('test-results', \Illuminate\Support\Str::uuid()->toString().'.'.$ext, 'public');
                $metadata['test_result_attachment_path'] = $storedPath;
                $metadata['test_result_attachment_original'] = $this->test_result_file->getClientOriginalName();
            }

            // Secondary Interpretation
            $hasSecondaryInput = $this->instrument_2 || $this->test_result_2 || $this->detected_substance_2 || $this->test_result_file_2;
            $multi = (isset($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) ? $metadata['multi_interpretations'] : [];

            if ($hasSecondaryInput) {
                $entry = is_array($multi) && count($multi) >= 1 && is_array($multi[0]) ? $multi[0] : [];
                if ($this->instrument_2) {
                    $entry['instrument'] = $this->instrument_2;
                }
                if ($this->test_result_2) {
                    $entry['test_result'] = $this->test_result_2;
                }
                if ($this->detected_substance_2) {
                    $entry['detected_substance'] = $this->detected_substance_2;
                }

                if ($this->test_result_file_2) {
                    if (! empty($entry['test_result_attachment_path']) && Storage::disk('public')->exists($entry['test_result_attachment_path'])) {
                        Storage::disk('public')->delete($entry['test_result_attachment_path']);
                    }
                    $ext = $this->test_result_file_2->getClientOriginalExtension();
                    $storedPath = $this->test_result_file_2->storeAs('test-results', \Illuminate\Support\Str::uuid()->toString().'.'.$ext, 'public');
                    $entry['test_result_attachment_path'] = $storedPath;
                    $entry['test_result_attachment_original'] = $this->test_result_file_2->getClientOriginalName();
                }
                $multi[0] = $entry;
                $metadata['multi_interpretations'] = $multi;
            } else {
                if (! empty($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) {
                    $entry = $metadata['multi_interpretations'][0] ?? null;
                    if (is_array($entry) && ! empty($entry['test_result_attachment_path']) && Storage::disk('public')->exists($entry['test_result_attachment_path'])) {
                        Storage::disk('public')->delete($entry['test_result_attachment_path']);
                    }
                    unset($metadata['multi_interpretations']);
                }
            }
        } else {
            // Clean up interpretation specific logic
            if (! empty($metadata['test_result_attachment_path']) && Storage::disk('public')->exists($metadata['test_result_attachment_path'])) {
                Storage::disk('public')->delete($metadata['test_result_attachment_path']);
            }
            if (! empty($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) {
                foreach ($metadata['multi_interpretations'] as $mi) {
                    if (is_array($mi) && ! empty($mi['test_result_attachment_path']) && Storage::disk('public')->exists($mi['test_result_attachment_path'])) {
                        Storage::disk('public')->delete($mi['test_result_attachment_path']);
                    }
                }
            }
            unset($metadata['instrument'], $metadata['test_result'], $metadata['detected_substance'], $metadata['test_result_attachment_path'], $metadata['test_result_attachment_original'], $metadata['multi_interpretations']);
        }

        if (empty($metadata)) {
            $metadata = null;
        }

        $this->process->update([
            'sample_id' => $this->sample_id,
            'stage' => $this->stage,
            'performed_by' => $this->performed_by ?: null,
            'started_at' => $this->started_at ?: null,
            'completed_at' => $this->completed_at ?: null,
            'notes' => $this->notes ?: null,
            'metadata' => $metadata,
        ]);

        if ($isInterpretationStage && ($metadata['test_result'] ?? null)) {
            // We use the controller's logic via a command or just defer for now (simpler approach: dispatch event that LHU needs formatting, but actually it's easier to just call the same LHU logic we had).
            // For brevity, we assume a job or unified service `DocumentService` handles it later, or we can port `ensureLhuNumber` + `createLhuDocument` if needed.
            // In a standard Livewire component, we'd inject the services and run it. I will omit the auto LHU generation for a second, let's keep it simple or call it via service.
            // Let's call the services directly.

            try {
                $numberingService = app(\App\Services\NumberingService::class);
                $docsService = app(\App\Services\DocumentService::class);
                $templateService = app(\App\Services\DocumentTemplateService::class);
                $pdfRenderService = app(\App\Services\PdfRenderService::class);

                // Ported LHU ensure logic inline to keep it simple:
                $lhuNumber = $metadata['lhu_number'] ?? $metadata['report_number'] ?? $metadata['lab_report_no'] ?? null;
                $sampleCode = $this->sample->sample_code ?? '';
                $sampleSeq = preg_match('/(?:^|[\/\-A-Z])(\d{3,5})(?:[\/\-A-Z]|$)/i', $sampleCode, $ms) ? (int) $ms[1] : null;
                $lhuSeq = preg_match('/(?:^|[\/\-A-Z])(\d{3,5})(?:[\/\-A-Z]|$)/i', $lhuNumber ?? '', $ml) ? (int) $ml[1] : null;

                if (empty($lhuNumber) || ($sampleSeq && $lhuSeq && $sampleSeq !== $lhuSeq)) {
                    $context = ['sample_id' => $this->sample->id, 'process_id' => $this->process->id, 'sample_code' => $sampleCode];
                    if ($sampleSeq) {
                        $context['forced_sequence'] = $sampleSeq;
                    }
                    $lhuNumber = $numberingService->issue('lhu', $context);

                    $metadata['lhu_number'] = $lhuNumber;
                    $this->process->update(['metadata' => $metadata]);
                }

                // Render Template
                $template = $templateService->getActiveTemplateByDocType('LHU');
                $html = null;
                $templateId = null;
                $templateVersion = null;
                $templateHash = null;

                $normalizeMethods = function ($rawMethods): array {
                    if (is_string($rawMethods)) {
                        $decoded = json_decode($rawMethods, true);
                        $rawMethods = is_array($decoded) ? $decoded : [$rawMethods];
                    }

                    if (! is_array($rawMethods)) {
                        return [];
                    }

                    return array_values(array_filter(array_map(function ($method) {
                        if (is_string($method)) {
                            $method = trim($method);

                            return $method !== '' ? $method : null;
                        }

                        return is_scalar($method) ? trim((string) $method) : null;
                    }, $rawMethods)));
                };

                $formatMethodLabel = function (string $method): string {
                    return match ($method) {
                        'gc_ms' => 'GC-MS (Gas Chromatography–Mass Spectrometry)',
                        'uv_vis' => 'UV-VIS (Ultraviolet–Visible Spectrophotometry)',
                        'lc_ms' => 'LC-MS (Liquid Chromatography–Mass Spectrometry)',
                        default => str($method)->replace('_', ' ')->title()->toString(),
                    };
                };

                $methodSummary = collect([
                    $metadata['test_method'] ?? null,
                    $metadata['method'] ?? null,
                    $this->process->method ?? null,
                    $this->process->test_method ?? null,
                ])
                    ->filter(fn ($method) => is_string($method) && trim($method) !== '')
                    ->merge($normalizeMethods($this->sample->test_methods ?? []))
                    ->map(fn ($method) => $formatMethodLabel((string) $method))
                    ->unique()
                    ->values()
                    ->join(', ');

                $forcedActive = $this->sample->active_substance ?? null;
                $detectedSubstance = $metadata['detected_substance'] ?? $metadata['detection'] ?? $metadata['hasil'] ?? $forcedActive;
                $testResultText = match ($metadata['test_result'] ?? null) {
                    'positive' => 'Positif', 'negative' => 'Negatif', default => 'Belum ditentukan'
                };
                $instrument = $metadata['instrument'] ?? $metadata['instrument_pengujian'] ?? ($methodSummary !== '' ? $methodSummary : null);

                if ($template) {
                    $data = [
                        'lhu_number' => $lhuNumber,
                        'report_number' => $lhuNumber,
                        'request_number' => $this->sample->testRequest->request_number ?? 'N/A',
                        'case_number' => $this->sample->testRequest->case_number ?? 'N/A',
                        'generated_at' => now()->format('d F Y'),
                        'investigator_name' => $this->sample->testRequest->investigator->name ?? 'N/A',
                        'investigator_nrp' => $this->sample->testRequest->investigator->nrp ?? 'N/A',
                        'investigator_rank' => $this->sample->testRequest->investigator->rank ?? 'N/A',
                        'investigator_jurisdiction' => $this->sample->testRequest->investigator->jurisdiction ?? 'N/A',
                        'short_description' => $this->sample->short_description ?? 'N/A',
                        'sample_code' => $this->sample->sample_code ?? 'N/A',
                        'sample_type' => $this->sample->sample_form ?? 'N/A',
                        'sample_weight' => $this->sample->sample_weight ?? 'N/A',
                        'package_quantity' => $this->sample->package_quantity ?? 1,
                        'unit' => $this->sample->unit ?? 'N/A',
                        'test_date' => $this->process->completed_at?->format('d F Y') ?? now()->format('d F Y'),
                        'test_methods' => $methodSummary !== '' ? $methodSummary : 'N/A',
                        'analyst_name' => $this->process->analyst->name ?? 'N/A',
                        'active_substance' => $forcedActive ?? 'Belum dianalisis',
                        'detected_substance' => $detectedSubstance ?? 'Tidak terdeteksi',
                        'instrument' => $instrument ?? 'N/A',
                        'test_result' => $testResultText,
                        'test_result_text' => $testResultText,
                        'conclusion' => "Barang bukti mengandung {$detectedSubstance}",
                        'lab_name' => 'Pusdokkes Polri',
                        'lab_address' => 'Jakarta',
                    ];
                    $html = $templateService->renderHtmlFromTemplate($template, $data);
                    $templateId = $template->id;
                    $templateVersion = $template->version;
                    $templateHash = $templateService->calculateTemplateHash($template);
                } else {
                    $html = view('pdf.laporan-hasil-uji', [
                        'process' => $this->process,
                        'generatedAt' => now(),
                        'noLHU' => $lhuNumber,
                        'forcedActiveSubstance' => $forcedActive,
                    ])->render();
                }

                $pdf = $pdfRenderService->htmlToPdf($html, config('app.url'));
                $base = $docsService->generateDocumentBaseName('lhu', $lhuNumber);
                $docsService->storeForSampleProcess($this->process, 'html', 'laporan_hasil_uji_html', $base, $html, replaceExisting: true);
                $docPdf = $docsService->storeForSampleProcess($this->process, 'pdf', 'laporan_hasil_uji', $base, $pdf, replaceExisting: true);

                if ($docPdf && $templateId) {
                    $extra = $docPdf->extra ?? [];
                    $extra['template_id'] = $templateId;
                    $extra['template_version'] = $templateVersion;
                    $extra['template_hash'] = $templateHash;
                    $docPdf->extra = $extra;
                    $docPdf->save();
                }

            } catch (\Exception $e) {
                $lhuGenerationFailed = true;
                $lhuGenerationMessage = 'Data workstation tersimpan, tetapi dokumen LHU gagal dibuat otomatis. Silakan cek template/dokumen hasil uji lalu coba lagi.';
                report($e);
            }
        }

        $this->loadProcess($this->process->id);
        $this->dispatch(
            'notify',
            title: $lhuGenerationFailed ? 'Sebagian Berhasil' : 'Berhasil',
            message: $lhuGenerationFailed ? $lhuGenerationMessage : 'Workstation berhasil diperbarui.',
            type: $lhuGenerationFailed ? 'error' : 'success'
        );
        $this->dispatch('sample-process-updated');
    }
}
