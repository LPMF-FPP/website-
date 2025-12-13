<?php

namespace App\Http\Controllers;

use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Services\WorkflowService;
use App\Services\ActiveSubstanceService;
use App\Enums\TestProcessStage;
use App\Enums\SampleStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Document;

class SampleTestProcessController extends Controller
{
    protected $workflowService;
    protected ActiveSubstanceService $activeSubstanceService;

    public function __construct(WorkflowService $workflowService, ActiveSubstanceService $activeSubstanceService)
    {
        $this->workflowService = $workflowService;
        $this->activeSubstanceService = $activeSubstanceService;
    }

    public function index(Request $request): View
    {
        $query = SampleTestProcess::with(['sample.testRequest.investigator', 'analyst'])
            ->whereHas('sample', function($q) {
                // Hanya proses dari sampel yang sudah diinput pengujian DAN belum ready_for_delivery
                $q->whereNotNull('assigned_analyst_id')
                  ->whereNotNull('test_date')
                  ->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value);
            });

        if ($request->filled('stage')) {
            $query->where('stage', $request->string('stage'));
        }

        // Filter by exact sample_name if provided (dropdown)
        if ($request->filled('sample_name')) {
            $name = $request->string('sample_name');
            $query->whereHas('sample', function($q) use ($name) {
                $q->where('sample_name', $name);
            });
        }

        // Filter by exact request_number if provided (dropdown)
        if ($request->filled('request_number')) {
            $reqNo = $request->string('request_number');
            $query->whereHas('sample.testRequest', function($q) use ($reqNo) {
                $q->where('request_number', $reqNo);
            });
        }

        $processes = $query->latest()
            ->paginate(20)
            ->withQueryString();

        // Hanya tampilkan sampel yang sudah diinput data pengujiannya DAN belum ready_for_delivery
        $samples = Sample::with('testRequest')
            ->whereNotNull('assigned_analyst_id')
            ->whereNotNull('test_date')
            ->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)
            ->latest()
            ->get();

        // Build dropdown options from eligible samples only
        $sampleNames = $samples->pluck('sample_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $requestNumbers = $samples->pluck('testRequest.request_number')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Get samples that have all 3 stages completed for "Ready for Delivery" action
        $samplesReadyForDelivery = Sample::whereHas('testProcesses', function($q) {
            $q->whereNotNull('completed_at')
              ->whereIn('stage', ['preparation', 'instrumentation', 'interpretation']);
        }, '=', 3)
        ->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)
        ->pluck('id')
        ->toArray();

        return view('sample-processes.index', [
            'processes' => $processes,
            'samples' => $samples,
            'stages' => TestProcessStage::cases(),
            'filters' => $request->only(['stage', 'sample_name', 'request_number']),
            'samplesReadyForDelivery' => $samplesReadyForDelivery,
            'sampleNames' => $sampleNames,
            'requestNumbers' => $requestNumbers,
        ]);
    }

    public function create(): View
    {
        // Hanya tampilkan sampel yang sudah diinput data pengujiannya DAN belum ready_for_delivery
        $samples = Sample::with('testRequest')
            ->whereNotNull('assigned_analyst_id')
            ->whereNotNull('test_date')
            ->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)
            ->latest()
            ->get();

        $analysts = \App\Models\User::where('role', 'analyst')
            ->orWhere('role', 'admin')
            ->orderBy('name')
            ->get();

        // Exclude ADMINISTRATION stage from selectable options on create page
        $stages = collect(TestProcessStage::cases())
            ->reject(fn($stage) => $stage === TestProcessStage::ADMINISTRATION)
            ->mapWithKeys(fn($stage) => [$stage->value => $stage->label()])
            ->toArray();

        return view('sample-processes.create', [
            'samples' => $samples,
            'analysts' => $analysts,
            'stages' => $stages,
            'process' => null, // For create, process is null
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sample_id' => ['required', 'exists:samples,id'],
            'stage' => ['required', 'string', Rule::in(array_column(TestProcessStage::cases(), 'value'))],
            'performed_by' => ['nullable', 'exists:users,id'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string'],
            'metadata_raw' => ['nullable', 'string'],
        ]);

        // Cek apakah kombinasi sample_id + stage sudah ada
        $exists = SampleTestProcess::where('sample_id', $validated['sample_id'])
            ->where('stage', $validated['stage'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['stage' => 'Kombinasi sampel dan tahapan ini sudah ada. Silakan pilih tahapan lain atau sampel lain.'])
                ->withInput();
        }

        $metadata = null;
        $metadataRawInput = $validated['metadata_raw'] ?? null;

        if ($metadataRawInput !== null) {
            $trimmed = trim($metadataRawInput);
            if ($trimmed !== '') {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['metadata_raw' => 'Format JSON tidak valid.'])->withInput();
                }
                $metadata = $decoded;
            }
        }

        $sampleProcess = SampleTestProcess::create([
            'sample_id' => $validated['sample_id'],
            'stage' => $validated['stage'],
            'performed_by' => $validated['performed_by'] ?? null,
            'started_at' => $validated['started_at'] ?? null,
            'completed_at' => $validated['completed_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'metadata' => $metadata
        ]);

        return redirect()
            ->route('sample-processes.show', $sampleProcess)
            ->with('success', 'Proses pengujian berhasil dibuat.');
    }

    public function show(SampleTestProcess $sampleProcess): View
    {
        $sampleProcess->load(['sample.testRequest.investigator', 'sample.testProcesses', 'analyst']);

        $interpretationDetails = null;
        if ($sampleProcess->stage === TestProcessStage::INTERPRETATION) {
            $metadata = $sampleProcess->metadata ?? [];
            $instrument = $metadata['instrument'] ?? $metadata['instrument_pengujian'] ?? $metadata['instrumentation'] ?? null;
            $detected = $metadata['detected_substance'] ?? $metadata['detection'] ?? $metadata['hasil'] ?? $sampleProcess->sample?->active_substance ?? null;
            $resultRaw = $metadata['test_result'] ?? null;
            $resultLabel = match ($resultRaw) {
                'positive' => 'Positif',
                'negative' => 'Negatif',
                default => 'Belum ditentukan',
            };
            $reportNumber = $sampleProcess->report_number
                ?? $sampleProcess->lab_report_no
                ?? $metadata['report_number']
                ?? $this->computeFLHUFromSampleCode($sampleProcess->sample);

            // Check if report document exists in documents table
            $reportDoc = \App\Models\Document::where('test_request_id', $sampleProcess->sample->test_request_id)
                ->whereIn('document_type', ['laporan_hasil_uji', 'lab_report'])
                ->latest()
                ->first();

            $attachmentPath = $metadata['test_result_attachment_path'] ?? null;
            $attachmentOriginal = $metadata['test_result_attachment_original'] ?? null;
            $attachmentUrl = $attachmentPath && Storage::disk('public')->exists($attachmentPath)
                ? asset('storage/' . ltrim($attachmentPath, '/'))
                : null;

            // Prepare multi-instrument interpretations if present
            $multi = [];
            if (!empty($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) {
                foreach ($metadata['multi_interpretations'] as $mi) {
                    if (!is_array($mi)) { continue; }
                    $raw = $mi['test_result'] ?? null;
                    $label = match ($raw) { 'positive' => 'Positif', 'negative' => 'Negatif', default => 'Belum ditentukan' };
                    $path = $mi['test_result_attachment_path'] ?? null;
                    $url = $path && Storage::disk('public')->exists($path) ? asset('storage/' . ltrim($path, '/')) : null;
                    $multi[] = [
                        'instrument' => $mi['instrument'] ?? null,
                        'detected_substance' => $mi['detected_substance'] ?? null,
                        'test_result' => $label,
                        'test_result_raw' => $raw,
                        'attachment_url' => $url,
                        'attachment_original' => $mi['test_result_attachment_original'] ?? null,
                    ];
                }
            }

            $interpretationDetails = [
                'instrument' => $instrument ?: 'Belum ditentukan',
                'detected_substance' => $detected ?: 'Tidak ada hasil terdeteksi',
                'test_result' => $resultLabel,
                'test_result_raw' => $resultRaw,
                'report_number' => $reportNumber,
                'report_document' => $reportDoc,
                'report_exists' => $reportDoc !== null,
                'attachment_path' => $attachmentPath,
                'attachment_original' => $attachmentOriginal,
                'attachment_url' => $attachmentUrl,
                'multi' => $multi,
            ];
        }

        return view('sample-processes.show', [
            'sampleProcess' => $sampleProcess,
            'stages' => TestProcessStage::cases(),
            'interpretationDetails' => $interpretationDetails
        ]);
    }

    public function edit(SampleTestProcess $sampleProcess): View
    {
        $sampleProcess->load(['sample', 'analyst']);

        // Hanya tampilkan sampel yang belum ready_for_delivery
        $samples = Sample::with('testRequest')
            ->whereNotNull('assigned_analyst_id')
            ->whereNotNull('test_date')
            ->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)
            ->latest()
            ->get();

        $analysts = \App\Models\User::where('role', 'analyst')
            ->orWhere('role', 'admin')
            ->orderBy('name')
            ->get();

        // Exclude ADMINISTRATION stage from selectable options on edit page
        $stages = collect(TestProcessStage::cases())
            ->reject(fn($stage) => $stage === TestProcessStage::ADMINISTRATION)
            ->mapWithKeys(fn($stage) => [$stage->value => $stage->label()])
            ->toArray();

        $metadata = $sampleProcess->metadata ?? [];
        $activeSubstancesData = $this->activeSubstanceService->breakdown(0);
        $activeSubstances = collect($activeSubstancesData['labels'] ?? [])
            ->filter()
            ->values();

        $currentDetectedSubstance = $metadata['detected_substance']
            ?? $metadata['detection']
            ?? $metadata['hasil']
            ?? $sampleProcess->sample?->active_substance
            ?? null;

        if ($currentDetectedSubstance && !$activeSubstances->contains($currentDetectedSubstance)) {
            $activeSubstances = $activeSubstances->prepend($currentDetectedSubstance);
        }

        $currentTestResult = $metadata['test_result'] ?? null;
        $currentInstrument = $metadata['instrument'] ?? $metadata['instrument_pengujian'] ?? null;
        $attachmentPath = $metadata['test_result_attachment_path'] ?? null;
        $attachmentOriginal = $metadata['test_result_attachment_original'] ?? null;
        $attachmentUrl = $attachmentPath && Storage::disk('public')->exists($attachmentPath)
            ? asset('storage/' . ltrim($attachmentPath, '/'))
            : null;

        // Secondary (optional) interpretation for multi-instrument cases
        $multi = (isset($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations']))
            ? $metadata['multi_interpretations']
            : [];
        $secondary = is_array($multi) && count($multi) >= 1 && is_array($multi[0]) ? $multi[0] : [];
        $secondaryInstrument = $secondary['instrument'] ?? null;
        $secondaryTestResult = $secondary['test_result'] ?? null;
        $secondaryDetected = $secondary['detected_substance'] ?? null;
        $secondaryAttachmentPath = $secondary['test_result_attachment_path'] ?? null;
        $secondaryAttachmentOriginal = $secondary['test_result_attachment_original'] ?? null;
        $secondaryAttachmentUrl = $secondaryAttachmentPath && Storage::disk('public')->exists($secondaryAttachmentPath)
            ? asset('storage/' . ltrim($secondaryAttachmentPath, '/'))
            : null;

        return view('sample-processes.edit', [
            'process' => $sampleProcess,
            'samples' => $samples,
            'analysts' => $analysts,
            'stages' => $stages,
            'activeSubstances' => $activeSubstances,
            'currentDetectedSubstance' => $currentDetectedSubstance,
            'currentTestResult' => $currentTestResult,
            'currentInstrument' => $currentInstrument,
            'currentResultAttachmentPath' => $attachmentPath,
            'currentResultAttachmentOriginal' => $attachmentOriginal,
            'currentResultAttachmentUrl' => $attachmentUrl,
            // Secondary
            'secondaryInstrument' => $secondaryInstrument,
            'secondaryTestResult' => $secondaryTestResult,
            'secondaryDetectedSubstance' => $secondaryDetected,
            'secondaryResultAttachmentPath' => $secondaryAttachmentPath,
            'secondaryResultAttachmentOriginal' => $secondaryAttachmentOriginal,
            'secondaryResultAttachmentUrl' => $secondaryAttachmentUrl,
        ]);
    }

    public function generateForm(SampleTestProcess $sampleProcess, string $stage)
    {
        $sampleProcess->loadMissing(['sample.testRequest.investigator']);

        $html = view('pdf.form-preparation', [
            'process'     => $sampleProcess,
            'generatedAt' => now(),
        ])->render();

        $pdfBinary = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96)
            ->output();

        $docs = app(\App\Services\DocumentService::class);
        $reqNo = $sampleProcess->sample->testRequest->request_number;
        $sampleCode = $sampleProcess->sample->sample_code ?? ('SAMPLE-'.$sampleProcess->sample_id);
        $baseName = "Form-Preparasi-{$sampleCode}-{$reqNo}";

        $doc = $docs->storeForSampleProcess($sampleProcess, 'pdf', 'form_preparation', $baseName, $pdfBinary);

        if (request()->boolean('download')) {
            return response()->download(
                storage_path('app/public/'.$doc->path),
                $doc->filename,
                ['Content-Type' => 'application/pdf']
            );
        }

        return response($pdfBinary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$doc->filename.'"',
        ]);
    }

    public function update(Request $request, SampleTestProcess $sampleProcess): RedirectResponse
    {
        $validated = $request->validate([
            'sample_id' => ['required', 'exists:samples,id'],
            'stage' => ['required', 'string', Rule::in(array_column(TestProcessStage::cases(), 'value'))],
            'performed_by' => ['nullable', 'exists:users,id'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string'],
            'metadata_raw' => ['nullable', 'string'],
            'instrument' => ['nullable', 'string', 'max:255'],
            'test_result' => ['nullable', 'string', Rule::in(['positive', 'negative'])],
            'detected_substance' => ['nullable', 'string', 'max:255'],
            'test_result_file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
            // Secondary (optional) interpretation for multi-instrument
            'instrument_2' => ['nullable', 'string', 'max:255'],
            'test_result_2' => ['nullable', 'string', Rule::in(['positive', 'negative'])],
            'detected_substance_2' => ['nullable', 'string', 'max:255'],
            'test_result_file_2' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        // Cek apakah kombinasi sample_id + stage sudah ada (kecuali record ini sendiri)
        $exists = SampleTestProcess::where('sample_id', $validated['sample_id'])
            ->where('stage', $validated['stage'])
            ->where('id', '!=', $sampleProcess->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['stage' => 'Kombinasi sampel dan tahapan ini sudah ada. Silakan pilih tahapan lain atau sampel lain.'])
                ->withInput();
        }

        $metadata = $sampleProcess->metadata ?? [];
        $metadataRawInput = $validated['metadata_raw'] ?? null;

        if ($metadataRawInput !== null) {
            $trimmed = trim($metadataRawInput);
            if ($trimmed === '') {
                $metadata = [];
            } else {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['metadata_raw' => 'Format JSON tidak valid.'])->withInput();
                }
                $metadata = array_merge($metadata, $decoded);
            }
        }

        $isInterpretationStage = $validated['stage'] === TestProcessStage::INTERPRETATION->value;

        if ($isInterpretationStage) {
            // Save instrument
            $instrumentValue = $validated['instrument'] ?? null;
            if ($instrumentValue) {
                $metadata['instrument'] = $instrumentValue;
            } elseif (array_key_exists('instrument', $metadata)) {
                unset($metadata['instrument']);
            }

            $resultValue = $validated['test_result'] ?? null;
            if ($resultValue) {
                $metadata['test_result'] = $resultValue;
            } elseif (array_key_exists('test_result', $metadata)) {
                unset($metadata['test_result']);
            }

            $detectedValue = $validated['detected_substance'] ?? null;
            if ($detectedValue) {
                $metadata['detected_substance'] = $detectedValue;
            } elseif (array_key_exists('detected_substance', $metadata)) {
                unset($metadata['detected_substance']);
            }

            if ($request->hasFile('test_result_file')) {
                $file = $request->file('test_result_file');
                if (!is_array($metadata)) {
                    $metadata = [];
                }
                if (!empty($metadata['test_result_attachment_path']) && Storage::disk('public')->exists($metadata['test_result_attachment_path'])) {
                    Storage::disk('public')->delete($metadata['test_result_attachment_path']);
                }
                $storedPath = $file->storeAs(
                    'test-results',
                    Str::uuid()->toString() . '.' . $file->getClientOriginalExtension(),
                    'public'
                );
                $metadata['test_result_attachment_path'] = $storedPath;
                $metadata['test_result_attachment_original'] = $file->getClientOriginalName();
            }

            // Handle secondary interpretation (optional)
            $hasSecondaryInput = ($validated['instrument_2'] ?? null) || ($validated['test_result_2'] ?? null) || ($validated['detected_substance_2'] ?? null) || $request->hasFile('test_result_file_2');
            $multi = (isset($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) ? $metadata['multi_interpretations'] : [];
            if ($hasSecondaryInput) {
                $entry = is_array($multi) && count($multi) >= 1 && is_array($multi[0]) ? $multi[0] : [];
                if ($validated['instrument_2'] ?? null) {
                    $entry['instrument'] = $validated['instrument_2'];
                }
                if ($validated['test_result_2'] ?? null) {
                    $entry['test_result'] = $validated['test_result_2'];
                }
                if ($validated['detected_substance_2'] ?? null) {
                    $entry['detected_substance'] = $validated['detected_substance_2'];
                }
                if ($request->hasFile('test_result_file_2')) {
                    $file2 = $request->file('test_result_file_2');
                    if (!empty($entry['test_result_attachment_path']) && Storage::disk('public')->exists($entry['test_result_attachment_path'])) {
                        Storage::disk('public')->delete($entry['test_result_attachment_path']);
                    }
                    $storedPath2 = $file2->storeAs(
                        'test-results',
                        Str::uuid()->toString() . '.' . $file2->getClientOriginalExtension(),
                        'public'
                    );
                    $entry['test_result_attachment_path'] = $storedPath2;
                    $entry['test_result_attachment_original'] = $file2->getClientOriginalName();
                }
                $multi[0] = $entry;
                $metadata['multi_interpretations'] = $multi;
            } else {
                // If no secondary input and exists previously but now cleared, remove it and delete file
                if (!empty($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) {
                    $entry = $metadata['multi_interpretations'][0] ?? null;
                    if (is_array($entry) && !empty($entry['test_result_attachment_path']) && Storage::disk('public')->exists($entry['test_result_attachment_path'])) {
                        Storage::disk('public')->delete($entry['test_result_attachment_path']);
                    }
                    unset($metadata['multi_interpretations']);
                }
            }
        } else {
            // Clean up interpretation-specific metadata if stage is not interpretation
            if (!empty($metadata['test_result_attachment_path']) && Storage::disk('public')->exists($metadata['test_result_attachment_path'])) {
                Storage::disk('public')->delete($metadata['test_result_attachment_path']);
            }
            // Clean up secondary attachments if any
            if (!empty($metadata['multi_interpretations']) && is_array($metadata['multi_interpretations'])) {
                foreach ($metadata['multi_interpretations'] as $mi) {
                    if (is_array($mi) && !empty($mi['test_result_attachment_path']) && Storage::disk('public')->exists($mi['test_result_attachment_path'])) {
                        Storage::disk('public')->delete($mi['test_result_attachment_path']);
                    }
                }
            }
            unset($metadata['instrument'], $metadata['test_result'], $metadata['detected_substance'], $metadata['test_result_attachment_path'], $metadata['test_result_attachment_original'], $metadata['multi_interpretations']);
        }

        if (empty($metadata)) {
            $metadata = null;
        }

        $sampleProcess->update([
            'sample_id' => $validated['sample_id'],
            'stage' => $validated['stage'],
            'performed_by' => $validated['performed_by'] ?? null,
            'started_at' => $validated['started_at'] ?? null,
            'completed_at' => $validated['completed_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'metadata' => $metadata
        ]);

        return redirect()
            ->route('sample-processes.show', $sampleProcess)
            ->with('success', 'Proses pengujian berhasil diperbarui.');
    }

    public function generateReport(SampleTestProcess $sampleProcess, \App\Services\DocumentService $docs)
    {
        $sampleProcess->load(['sample.testRequest.investigator']);

        // Validate that sample exists
        if (!$sampleProcess->sample) {
            abort(404, 'Sample not found for this process');
        }

        // Set & persist nomor LHU if empty (stored in metadata)
        $metadata = $sampleProcess->metadata ?? [];
        if (empty($metadata['report_number']) && empty($metadata['lab_report_no']) && empty($metadata['lhu_number'])) {
            $metadata['report_number'] = $this->computeFLHUFromSampleCode($sampleProcess->sample);
            $sampleProcess->metadata = $metadata;
            $sampleProcess->save();
        }

        // Force hasil dari ZAT AKTIF sampel
        $forcedActive = $sampleProcess->sample->active_substance ?? null;

        // Render HTML
        $html = view('pdf.laporan-hasil-uji', [
            'process'               => $sampleProcess,
            'generatedAt'           => now(),
            'noLHU'                 => ($metadata['report_number'] ?? $metadata['lab_report_no'] ?? $metadata['lhu_number'] ?? null),
            'forcedActiveSubstance' => $forcedActive,
        ])->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96)
            ->output();

        // SAVE via DocumentService (html + pdf)
        $reqNo = $sampleProcess->sample->testRequest->request_number ?? 'REQ-UNKNOWN';
        $code  = $sampleProcess->sample->sample_code ?? ('SAMPLE-'.$sampleProcess->sample_id);
        $noLHU = $metadata['report_number'] ?? $metadata['lab_report_no'] ?? null;
        $base  = trim("Laporan-Hasil-Uji-{$code}-{$reqNo}".($noLHU ? "-{$noLHU}" : ''));

        $docs->storeForSampleProcess($sampleProcess, 'html', 'laporan_hasil_uji_html', $base, $html);
        $docPdf = $docs->storeForSampleProcess($sampleProcess, 'pdf',  'laporan_hasil_uji',      $base, $pdf);

        // respond inline or download
        if (request()->boolean('download')) {
            return response()->download(
                storage_path('app/public/'.$docPdf->path),
                $docPdf->filename,
                ['Content-Type' => 'application/pdf']
            );
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docPdf->filename.'"',
        ]);
    }

    private function computeFLHUFromSampleCode(?Sample $sample): string
    {
        $code = (string)($sample->sample_code ?? '');
        // Expected pattern: W%03d + RomanMonth + Year, e.g. W012V2025 -> FLHU012
        if (preg_match('/^W(\d{3})/i', $code, $m)) {
            return 'FLHU' . $m[1];
        }
        // Fallback: take first 3 digits consecutively in code
        if (preg_match('/.*?(\d{3})/', $code, $m2)) {
            return 'FLHU' . $m2[1];
        }
        // Final fallback: 001
        return 'FLHU001';
    }

    protected function generateNextReportNumber(): string
    {
        $metadatas = SampleTestProcess::where('stage', TestProcessStage::INTERPRETATION->value)
            ->whereNotNull('metadata')
            ->pluck('metadata');

        $max = 0;
        foreach ($metadatas as $data) {
            if (!is_array($data)) {
                continue;
            }
            $value = $data['report_number'] ?? null;
            if (!$value) {
                continue;
            }
            $numeric = (int) preg_replace('/\D/', '', $value);
            if ($numeric > $max) {
                $max = $numeric;
            }
        }

        return sprintf('FLHU%03d', $max + 1);
    }

    protected function formatReportNumberCandidate(int $id): string
    {
        return sprintf('FLHU%03d', max($id, 1));
    }

    public function startProcess(Request $request, Sample $sample): RedirectResponse
    {
        $request->validate([
            'stage' => ['required', 'string', Rule::in(array_column(TestProcessStage::cases(), 'value'))]
        ]);

        $stage = TestProcessStage::from($request->stage);

        try {
            $process = $this->workflowService->startTestProcess($sample, $stage);
            return back()->with('success', "Tahap {$stage->label()} telah dimulai.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function completeProcess(Request $request, SampleTestProcess $process): RedirectResponse
    {
        try {
            $this->workflowService->completeTestProcess($process);
            return back()->with('success', "Tahap {$process->stage->label()} telah selesai.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function destroy(SampleTestProcess $sampleProcess): RedirectResponse
    {
        $sampleId = $sampleProcess->sample_id;
        $sampleProcess->delete();

        return redirect()
            ->route('sample-processes.index')
            ->with('success', 'Proses pengujian berhasil dihapus.');
    }

    public function markAsReadyForDelivery(Sample $sample): RedirectResponse
    {
        // Check if all 3 stages are completed
        $completedStages = $sample->testProcesses()
            ->whereNotNull('completed_at')
            ->whereIn('stage', ['preparation', 'instrumentation', 'interpretation'])
            ->count();

        if ($completedStages < 3) {
            return back()->withErrors(['error' => 'Semua tahap pengujian harus selesai terlebih dahulu.']);
        }

        // Update sample status to ready for delivery
        $sample->update([
            'status' => SampleStatus::READY_FOR_DELIVERY
        ]);

        // Check if all samples in this request are ready for delivery
        $testRequest = $sample->testRequest;
        $allSamplesReady = $testRequest->samples()
            ->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)
            ->count() === 0;

        if ($allSamplesReady) {
            // Set completed_at timestamp when all samples are ready
            $testRequest->update([
                'status' => 'ready_for_delivery',
                'completed_at' => now()
            ]);
        }

        return redirect()
            ->route('delivery.show', $testRequest)
            ->with('success', 'Sampel berhasil dikirim ke penyerahan.');
    }
}
