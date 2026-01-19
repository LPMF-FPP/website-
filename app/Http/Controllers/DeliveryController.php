<?php

namespace App\Http\Controllers;

use App\Models\CustomerSurvey;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\RemainingUnit;
use App\Models\TestRequest;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    private function handoverBasePath(string $requestNumber): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]+/', '_', $requestNumber);

        return base_path("output/BA_Penyerahan_Ringkasan_{$sanitized}");
    }

    public function index()
    {
        $requests = TestRequest::with([
            'investigator:id,name,jurisdiction,rank',
            'samples' => function ($query) {
                $query->select('id', 'test_request_id', 'short_description', 'sample_code')
                    ->with(['testProcesses' => function ($q) {
                        $q->select('id', 'sample_id', 'stage', 'completed_at')
                            ->whereNotNull('completed_at')
                            ->whereIn('stage', ['preparation', 'instrumentation', 'interpretation']);
                    }])
                    ->withCount(['testProcesses as completed_stages' => function ($q) {
                        $q->whereNotNull('completed_at')
                            ->whereIn('stage', ['preparation', 'instrumentation', 'interpretation']);
                    }]);
            },
        ])
            ->where(function ($query) {
                $query->where('status', 'ready_for_delivery');
            })
    // Include suspect_name and receipt_number for display
            ->select('id', 'request_number', 'receipt_number', 'investigator_id', 'suspect_name', 'status', 'completed_at')
            ->orderByDesc('completed_at')
            ->get();

        $deliveries = Delivery::with([

            'request.samples.testProcesses',

            'request.samples' => function ($query) {

                $query->whereHas('testProcesses', function ($q) {

                    $q->select('sample_id')

                        ->whereNotNull('completed_at')

                        ->whereIn('stage', ['preparation', 'instrumentation', 'interpretation'])

                        ->groupBy('sample_id')

                        ->havingRaw('COUNT(DISTINCT stage) = ?', [3]);

                });

            },

            'items',

        ])
            ->latest()
            ->paginate(10);

        return view('delivery.index', compact('requests', 'deliveries'));

    }

    public function show(TestRequest $request)
    {

        $request->load([

            'investigator',

            'samples.analyst',

            'samples.testProcesses.analyst',

            'evidenceUnits.remainingUnits', // Load data for labels

            'customerSurvey',

        ]);

        $formatQuantity = static function ($value): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            if (! is_numeric($value)) {
                return trim((string) $value) ?: null;
            }

            $number = (float) $value;
            $formatted = number_format($number, 2, '.', '');
            $formatted = rtrim(rtrim($formatted, '0'), '.');

            return $formatted === '' ? null : $formatted;
        };

        $appendUnit = static function (?string $quantity, ?string $unit): ?string {
            if ($quantity === null) {
                return null;
            }

            $unit = $unit ? trim($unit) : '';

            return $unit !== '' ? $quantity.' '.$unit : $quantity;
        };

        $request->samples->each(function ($sample) use ($formatQuantity, $appendUnit) {
            $deliveredQty = $sample->package_quantity;
            $testingQty = $sample->quantity;

            if ($deliveredQty !== null && ! is_numeric($deliveredQty)) {
                $deliveredQty = null;
            }

            if ($testingQty !== null && ! is_numeric($testingQty)) {
                $testingQty = null;
            }

            $leftoverQty = null;

            if ($deliveredQty !== null) {
                if ($testingQty !== null) {
                    $diff = (float) $deliveredQty - (float) $testingQty;
                    $leftoverQty = $diff > 0 ? $diff : 0.0;
                } else {
                    $leftoverQty = (float) $deliveredQty;
                }
            }

            $deliveredDisplay = $appendUnit($formatQuantity($deliveredQty), $sample->unit);
            $testingDisplay = $appendUnit($formatQuantity($testingQty), $sample->quantity_unit);
            $leftoverDisplay = $appendUnit(
                $formatQuantity($leftoverQty),
                $sample->unit ?? $sample->quantity_unit
            );

            $sample->setAttribute('delivered_quantity_value', $deliveredQty);
            $sample->setAttribute('delivered_quantity_display', $deliveredDisplay);
            $sample->setAttribute('testing_quantity_value', $testingQty);
            $sample->setAttribute('testing_quantity_display', $testingDisplay);
            $sample->setAttribute('leftover_quantity_value', $leftoverQty);
            $sample->setAttribute('leftover_quantity_display', $leftoverDisplay);
        });

        // Get or create delivery for this request
        $delivery = Delivery::firstOrCreate(
            ['request_id' => $request->id],
            [
                'delivered_by' => Auth::id(),
                'status' => \App\Enums\DeliveryStatus::PENDING ?? 'pending',
                'delivery_date' => now(),
            ]
        );

        // Auto-generate RemainingUnit labels for samples with leftover > 0
        $this->autoGenerateRemainingLabels($request);

        // Reload evidenceUnits with remainingUnits after auto-generation
        $request->load('evidenceUnits.remainingUnits');

        return view('delivery.show', [

            'request' => $request,
            'delivery' => $delivery,

            'stages' => [
                'preparation' => 'Preparasi Sampel',
                'instrumentation' => 'Pengujian Instrumen',
                'interpretation' => 'Interpretasi Hasil',
            ],

        ]);

    }

    public function surveyForm(TestRequest $request)
    {

        $request->loadMissing(['customerSurvey', 'investigator', 'samples']);

        $survey = $request->customerSurvey;
        $isReadOnly = $request->status === 'completed';

        return view('delivery.survey', compact('request', 'survey', 'isReadOnly'));

    }

    public function submitSurvey(Request $httpRequest, TestRequest $request)
    {

        if ($request->status === 'completed') {
            return back()->with('error', 'Penyerahan sudah selesai. Survei tidak dapat diubah.');
        }

        $jobCategories = [
            'TNI',
            'Polri',
            'ASN',
            'Swasta',
            'Wirausaha',
            'Mahasiswa',
            'Siswa',
        ];

        $requestTypes = ['Kimia - Fisika', 'Mikrobiologi'];

        $validatedData = $httpRequest->validate([
            'respondent_name' => ['required', 'string', 'max:255'],
            'respondent_institution' => ['required', 'string', 'max:255'],
            'respondent_job_category' => ['required', Rule::in($jobCategories)],
            'request_type' => ['required', Rule::in($requestTypes)],
            'voluntary_statement' => ['accepted'],
            'answers' => ['required', 'array'],
            'answers.persyaratan' => ['required', 'integer', 'between:1,4'],
            'answers.prosedur' => ['required', 'integer', 'between:1,4'],
            'answers.ketepatan_waktu' => ['required', 'integer', 'between:1,4'],
            'answers.kesesuaian_hasil' => ['required', 'integer', 'between:1,4'],
            'answers.kompetensi' => ['required', 'integer', 'between:1,4'],
            'answers.sikap' => ['required', 'integer', 'between:1,4'],
            'answers.pengaduan' => ['required', 'integer', 'between:1,4'],
            'answers.fasilitas' => ['required', 'integer', 'between:1,4'],
            'suggestion' => ['required', 'string'],
            'complaint' => ['nullable', 'string'],
            'follow_up' => ['nullable', 'string'],
        ], [
            'respondent_name.required' => 'Nama responden wajib diisi.',
            'respondent_institution.required' => 'Instansi responden wajib diisi.',
            'respondent_job_category.required' => 'Kategori pekerjaan wajib dipilih.',
            'respondent_job_category.in' => 'Kategori pekerjaan tidak valid.',
            'request_type.required' => 'Jenis permintaan pengujian wajib dipilih.',
            'request_type.in' => 'Jenis permintaan pengujian tidak valid.',
            'voluntary_statement.accepted' => 'Pernyataan wajib disetujui.',
            'answers.required' => 'Semua pertanyaan survei wajib diisi.',
            'answers.*.required' => 'Semua pertanyaan survei wajib diisi.',
            'answers.*.between' => 'Skor harus di antara 1 dan 4.',
            'suggestion.required' => 'Saran/pesan/masukan wajib diisi.',
        ]);

        $scoreAvg = collect($validatedData['answers'])
            ->map(fn ($value) => (int) $value)
            ->avg();

        CustomerSurvey::updateOrCreate(
            ['test_request_id' => $request->id],
            [
                'respondent_name' => $validatedData['respondent_name'],
                'respondent_institution' => $validatedData['respondent_institution'],
                'respondent_job_category' => $validatedData['respondent_job_category'],
                'request_type' => $validatedData['request_type'],
                'voluntary_statement' => true,
                'answers' => $validatedData['answers'],
                'suggestion' => $validatedData['suggestion'],
                'complaint' => $validatedData['complaint'] ?? null,
                'follow_up' => $validatedData['follow_up'] ?? null,
                'score_avg' => $scoreAvg,
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
            ]
        );

        return redirect()->route('delivery.show', $request)

            ->with('success', 'Terima kasih atas feedback Anda! Survei untuk permintaan '.$request->request_number.' telah tersimpan.');

    }

    public function markAsCompleted(Request $httpRequest, TestRequest $request)
    {
        // Validate that all samples are ready for delivery
        $notReadySamples = $request->samples()
            ->where('status', '!=', 'ready_for_delivery')
            ->count();

        if ($notReadySamples > 0) {
            return back()->withErrors(['error' => 'Semua sampel harus siap diserahkan terlebih dahulu.']);
        }

        $request->loadMissing('customerSurvey');

        if (! $request->customerSurvey || ! $request->customerSurvey->isComplete()) {
            return back()->with('error', 'Survey kepuasan wajib diisi sebelum penyerahan ditandai selesai.');
        }

        // Update status to completed
        $request->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Penyerahan berhasil diselesaikan. Status permintaan telah diperbarui.');
    }

    /**
     * Generate Berita Acara Penyerahan (Ringkasan 1 halaman) via Python script
     */
    public function generateHandoverSummary(TestRequest $request)
    {
        try {
            $python = 'python';
            $script = base_path('scripts/generate_ba_penyerahan_summary.py');
            $outdir = base_path('output');
            $templates = base_path('templates');

            if (! file_exists($script)) {
                return back()->with('error', 'Script generator BA Penyerahan tidak ditemukan.');
            }

            if (! is_dir($outdir)) {
                @mkdir($outdir, 0755, true);
            }

            // Build local payload to avoid network calls (more reliable in dev/prod)
            $request->loadMissing(['investigator', 'samples']);

            $formatTestMethods = function ($methods) {
                if (is_string($methods)) {
                    $methods = json_decode($methods, true) ?? [];
                }
                $map = [
                    'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',
                    'gc_ms' => 'Identifikasi GC-MS',
                    'lc_ms' => 'Identifikasi LC-MS',
                ];

                return collect($methods)->map(fn ($m) => $map[$m] ?? $m)->join('; ');
            };

            $formatQuantity = static function ($value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                if (! is_numeric($value)) {
                    return trim((string) $value) ?: null;
                }

                $number = (float) $value;
                $formatted = number_format($number, 2, '.', '');
                $formatted = rtrim(rtrim($formatted, '0'), '.');

                return $formatted === '' ? null : $formatted;
            };

            $appendUnit = static function (?string $quantity, ?string $unit): ?string {
                if ($quantity === null) {
                    return null;
                }

                $unit = $unit ? trim($unit) : '';

                return $unit !== '' ? $quantity.' '.$unit : $quantity;
            };

            // Load test processes to get report numbers
            $request->samples->load('testProcesses');

            // Get report numbers for samples
            $reportNumbers = $request->samples->map(function ($sample) {
                $interpProcess = $sample->testProcesses->where('stage', 'interpretation')->first();
                if ($interpProcess) {
                    $metadata = $interpProcess->metadata ?? [];

                    return $metadata['report_number'] ?? null;
                }

                return null;
            })->filter()->unique()->values();

            // Format sample code range (e.g., "W1X2025" or "W1X2025 - W1X2027")
            $sampleCodes = $request->samples->pluck('sample_code')->filter()->unique()->values();
            $sampleCodeRange = '';
            if ($sampleCodes->count() === 1) {
                $sampleCodeRange = $sampleCodes->first();
            } elseif ($sampleCodes->count() > 1) {
                $sampleCodeRange = $sampleCodes->first().' — '.$sampleCodes->last();
            }

            // Format report number range
            $reportNoRange = '';
            if ($reportNumbers->count() === 1) {
                $reportNoRange = $reportNumbers->first();
            } elseif ($reportNumbers->count() > 1) {
                $reportNoRange = $reportNumbers->first().' — '.$reportNumbers->last();
            }

            $payload = [
                'request_id' => $request->id,
                'request_no' => $request->request_number,
                'surat_permintaan_no' => $request->case_number ?? '',
                // for template compatibility: use case_number as 'request_basis' (nomor surat pada permintaan)
                'request_basis' => $request->case_number ?? '',
                'received_date' => $request->received_at ? $request->received_at->format('d F Y') : now()->format('d F Y'),
                'customer_rank_name' => trim(($request->investigator->rank ?? '').' '.($request->investigator->name ?? '')),
                'customer_no' => $request->investigator->nrp ?? '',
                'unit' => $request->investigator->jurisdiction ?? '',
                'suspect_name' => $request->suspect_name ?? '',
                'tests_summary' => $request->samples->map(fn ($s) => $formatTestMethods($s->test_methods))->unique()->join('; '),
                'sample_count' => $request->samples->count(),
                'sample_code_range' => $sampleCodeRange,
                'report_no_range' => $reportNoRange,
                'samples' => $request->samples->map(function ($sample) use ($formatTestMethods, $formatQuantity, $appendUnit) {
                    // package_quantity = jumlah yang diserahkan
                    // quantity = jumlah yang diuji (e.g., 5 tablet)
                    $deliveredQty = is_numeric($sample->package_quantity) ? (float) $sample->package_quantity : null;
                    $testingQty = is_numeric($sample->quantity) ? (float) $sample->quantity : null;

                    // Rumus: SISA = jumlah yang diserahkan - quantity
                    $leftoverQty = null;
                    if ($deliveredQty !== null) {
                        if ($testingQty !== null) {
                            $diff = $deliveredQty - $testingQty;
                            $leftoverQty = $diff > 0 ? $diff : 0.0;
                        } else {
                            $leftoverQty = $deliveredQty;
                        }
                    }

                    // Display format for delivered quantity (use unit if available)
                    $deliveredDisplay = $appendUnit($formatQuantity($deliveredQty), $sample->unit ?? $sample->quantity_unit);
                    $testingDisplay = $appendUnit($formatQuantity($testingQty), $sample->quantity_unit);
                    $leftoverDisplay = $appendUnit($formatQuantity($leftoverQty), $sample->quantity_unit);

                    // Get report number from interpretation process
                    $interpProcess = $sample->testProcesses->where('stage', 'interpretation')->first();
                    $reportNumber = null;
                    if ($interpProcess) {
                        $metadata = $interpProcess->metadata ?? [];
                        $reportNumber = $metadata['report_number'] ?? null;
                    }

                    return [
                        'code' => $sample->sample_code ?? null,
                        'short_description' => $sample->short_description,
                        'desc' => $sample->short_description ?? $sample->sample_description,
                        'tests' => $formatTestMethods($sample->test_methods),
                        'active' => $sample->active_substance ?? '',
                        'quantity' => $deliveredQty,
                        'quantity_display' => $deliveredDisplay,
                        'unit' => $sample->unit ?? null,
                        'testing_quantity' => $testingDisplay,
                        'leftover' => $leftoverDisplay,
                        'report_number' => $reportNumber,
                    ];
                })->values()->toArray(),
                'submitted_by' => trim(($request->investigator->rank ?? '').' '.($request->investigator->name ?? '')),
                'received_by' => 'Petugas Administrasi (dokumen) & Petugas Laboratorium (sampel)',
                'source_printed_at' => $request->submitted_at ? $request->submitted_at->format('d F Y H:i:s') : '',
            ];

            $tempDataFile = base_path('output/temp_ba_penyerahan_'.$request->request_number.'.json');
            file_put_contents($tempDataFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $process = new \Symfony\Component\Process\Process([
                $python,
                $script,
                '--id', $request->request_number,
                '--file', $tempDataFile,
                '--templates', $templates,
                '--outdir', $outdir,
                '--logo-tribrata', public_path('images/logo-tribrata-polri.png'),
                '--logo-pusdokkes', public_path('images/logo-pusdokkes-polri.png'),
                '--pdf',
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                \Illuminate\Support\Facades\Log::error('Generate BA Penyerahan gagal', [
                    'exit_code' => $process->getExitCode(),
                    'stdout' => $process->getOutput(),
                    'stderr' => $process->getErrorOutput(),
                ]);
                // Trim long stderr for flash
                $err = trim($process->getErrorOutput());
                $msg = 'Gagal generate Berita Acara Penyerahan.'.($err ? ' Detail: '.mb_strimwidth($err, 0, 300, '…') : '');
                // Clean up temp file
                if (file_exists($tempDataFile)) {
                    @unlink($tempDataFile);
                }

                return back()->with('error', $msg);
            }

            if (file_exists($tempDataFile)) {
                @unlink($tempDataFile);
            }

            return back()->with('success', 'Berita Acara Penyerahan (ringkasan) berhasil dibuat di folder output.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Exception generate BA Penyerahan', ['error' => $e->getMessage()]);

            return back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Generate/Regenerate BA Penyerahan (POST)
     */
    public function handoverGenerate(Delivery $delivery, DocumentService $docs)
    {
        $delivery->loadMissing(['request.investigator', 'request.samples']);
        $req = $delivery->request;
        $inv = $req->investigator;

        // Generate document number using the 'ba_penyerahan' numbering scope
        $numberingService = app(\App\Services\NumberingService::class);
        $baPenyerahanNumber = $numberingService->issue('ba_penyerahan', [
            'investigator_id' => $inv->id,
        ]);
        
        // Generate filesystem-safe baseName from document number
        $base = $docs->generateDocumentBaseName('ba_penyerahan', $baPenyerahanNumber);

        // render blade BA yang sudah kamu buat
        $html = view('pdf.ba-penyerahan', [
            'request' => $req,
            'generatedAt' => now(),
        ])->render();

        // arsip HTML (replace existing to avoid duplication)
        $docs->storeGenerated($html, 'html', $inv, $req, 'ba_penyerahan_html', $base, replaceExisting: true);

        // HTML → PDF
        $pdf = Pdf::loadHTML($html)->setPaper('a4')
            ->setOption('isRemoteEnabled', true)->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96)->output();

        // arsip PDF (replace existing to avoid duplication)
        $docs->storeGenerated($pdf, 'pdf', $inv, $req, 'ba_penyerahan', $base, replaceExisting: true);

        return back()->with('success', 'BA Penyerahan dibuat & disimpan di storage publik.');
    }

    /**
     * View BA Penyerahan inline (GET)
     *
     * This method only views existing document or generates on-the-fly without storing.
     * To store a new document, use handoverGenerate().
     */
    public function handoverView(Delivery $delivery, DocumentService $docs)
    {
        $delivery->loadMissing(['request.investigator', 'request.samples']);
        $req = $delivery->request;
        $inv = $req->investigator;

        // Check if PDF already exists - if so, serve it directly
        $existingPdf = $docs->getExistingGenerated($req, 'ba_penyerahan');
        if ($existingPdf && $docs->fileExists($existingPdf)) {
            $filePath = $docs->getFilePath($existingPdf);

            if (request()->boolean('download')) {
                return response()->download(
                    $filePath,
                    $existingPdf->filename,
                    ['Content-Type' => 'application/pdf']
                );
            }

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$existingPdf->filename.'"',
            ]);
        }

        // No existing PDF - generate on-the-fly WITHOUT storing (view only)
        $html = view('pdf.ba-penyerahan', [
            'request' => $req,
            'generatedAt' => now(),
        ])->render();

        // Konversi PDF (tanpa menyimpan)
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96)
            ->output();

        $filename = 'BA-Penyerahan-'.$req->request_number.'.pdf';

        if (request()->boolean('download')) {
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($pdf),
            ]);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Download BA Penyerahan as attachment (GET)
     */
    public function handoverDownload(Delivery $delivery)
    {
        $req = $delivery->loadMissing(['request'])->request;
        $doc = Document::where('test_request_id', $req->id)
            ->where('document_type', 'ba_penyerahan')->where('source', 'generated')
            ->latest()->firstOrFail();

        return response()->download(storage_path('app/public/'.$doc->path), $doc->filename, ['Content-Type' => 'application/pdf']);
    }

    public function handoverStatus(\App\Models\TestRequest $request)
    {
        // Query from documents table instead of filesystem
        $htmlDoc = \App\Models\Document::where('test_request_id', $request->id)
            ->where('document_type', 'ba_penyerahan_html')
            ->latest('created_at')
            ->first();

        $pdfDoc = \App\Models\Document::where('test_request_id', $request->id)
            ->where('document_type', 'ba_penyerahan')
            ->latest('created_at')
            ->first();

        $status = [
            'request_number' => $request->request_number,
            'html' => [
                'exists' => $htmlDoc !== null,
                'path' => $htmlDoc ? storage_path('app/public/'.$htmlDoc->path) : null,
                'mtime' => $htmlDoc ? $htmlDoc->created_at->toIso8601String() : null,
            ],
            'pdf' => [
                'exists' => $pdfDoc !== null,
                'path' => $pdfDoc ? storage_path('app/public/'.$pdfDoc->path) : null,
                'mtime' => $pdfDoc ? $pdfDoc->created_at->toIso8601String() : null,
            ],
        ];

        return response()->json($status);
    }

    /**
     * Auto-generate RemainingUnit labels for samples with leftover quantity > 0.
     * Skips samples that already have a RemainingUnit via their EvidenceUnit.
     */
    private function autoGenerateRemainingLabels(TestRequest $request): void
    {
        foreach ($request->samples as $sample) {
            // Get leftover quantity (already calculated and set as attribute)
            $leftoverQty = $sample->getAttribute('leftover_quantity_value');

            // Skip if no leftover or leftover <= 0
            if ($leftoverQty === null || $leftoverQty <= 0) {
                continue;
            }

            // Find or create EvidenceUnit for this sample
            $evidenceUnit = EvidenceUnit::firstOrCreate(
                ['sample_id' => $sample->id],
                [
                    'request_id' => $request->id,
                    'sample_code' => $sample->sample_code,
                    'sample_type' => $sample->sample_category ?? $sample->sample_form,
                    'sample_desc' => $sample->short_description ?? $sample->sample_description,
                    'investigator_name' => $request->investigator?->name,
                    'investigator_unit' => $request->investigator?->jurisdiction,
                    'received_at' => $sample->received_at ?? now(),
                    'received_by' => Auth::id(),
                ]
            );

            // Check if RemainingUnit already exists for this EvidenceUnit
            $existingRemaining = RemainingUnit::where('evidence_unit_id', $evidenceUnit->id)->exists();

            if ($existingRemaining) {
                // Skip - label already exists
                continue;
            }

            // Create RemainingUnit with leftover data
            RemainingUnit::create([
                'evidence_unit_id' => $evidenceUnit->id,
                'sample_code' => $sample->sample_code,
                'qty_remaining' => $leftoverQty,
                'uom' => $sample->unit ?? $sample->quantity_unit,
                'seal_status_delivered' => 'disegel',
                'delivered_at' => now(),
                'delivered_by' => Auth::id(),
            ]);
        }
    }
}
