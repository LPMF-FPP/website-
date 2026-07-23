<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\Suspect;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\ActiveSubstanceService;
use App\Services\DocumentGeneration\DocumentRenderService;
use App\Services\DocumentService;
use App\Services\GoogleDriveDocumentSyncService;
use App\Services\NumberingRepairService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RequestController extends Controller
{
    protected ActiveSubstanceService $activeSubstanceService;

    public function __construct(ActiveSubstanceService $activeSubstanceService)
    {

        $this->activeSubstanceService = $activeSubstanceService;

    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        // AMBIL DATA DARI DATABASE, kecuali yang sudah selesai
        // Hanya tampilkan request yang masih dalam proses (belum diserahkan)

        $requests = TestRequest::with(['investigator', 'samples', 'suspects'])
            ->whereNotIn('status', ['completed', 'ready_for_delivery'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('requests.index', compact('requests'));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $activeSubstances = $this->activeSubstanceService->breakdown(5);

        $activeSubstanceHighlights = [

            'total' => $activeSubstances['total'],

            'usingFallback' => $activeSubstances['fallback'] ?? false,

            'items' => [],

        ];

        foreach ($activeSubstances['labels'] as $index => $label) {

            $activeSubstanceHighlights['items'][] = [

                'label' => $label,

                'count' => $activeSubstances['data'][$index] ?? 0,

                'percentage' => $activeSubstances['percentages'][$index] ?? 0,

            ];

        }

        // Get existing investigators for autocomplete (Polri only)
        $existingInvestigators = Investigator::where('is_polri', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nrp', 'rank', 'jurisdiction', 'phone', 'address']);

        // Get existing non-Polri investigators for autocomplete
        $existingExternals = Investigator::where('is_polri', false)
            ->orderBy('name')
            ->get(['id', 'name', 'institution', 'phone', 'alt_phone', 'occupation']);

        // Get unique active substances from samples for autocomplete
        $existingActiveSubstances = Sample::whereNotNull('active_substance')
            ->where('active_substance', '!=', '')
            ->distinct()
            ->orderBy('active_substance')
            ->pluck('active_substance')
            ->unique()
            ->values();

        return view('requests.create', [
            'activeSubstanceHighlights' => $activeSubstanceHighlights,
            'existingInvestigators' => $existingInvestigators,
            'existingExternals' => $existingExternals,
            'existingActiveSubstances' => $existingActiveSubstances,
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Double-submit prevention using submission token
        $submissionToken = $request->input('_submission_token');
        if ($submissionToken) {
            $cacheKey = 'submission_token_'.$submissionToken;

            // Check if this token was already used
            if (Cache::has($cacheKey)) {
                Log::warning('Duplicate submission detected', [
                    'token' => $submissionToken,
                    'user_id' => auth()->id(),
                    'ip' => $request->ip(),
                ]);

                // Return the same redirect as success to avoid confusion
                return redirect()->route('requests.index')
                    ->with('warning', 'Permintaan ini sudah diproses sebelumnya.');
            }

        }

        // Additional lock based on user + timestamp to prevent race conditions
        $lockKey = 'request_store_user_'.auth()->id();
        $lock = Cache::lock($lockKey, 10); // 10 second lock

        if (! $lock->get()) {
            Log::warning('Request store locked - concurrent submission attempt', [
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            return back()->withInput()
                ->withErrors(['error' => 'Permintaan sedang diproses. Mohon tunggu beberapa saat.']);
        }

        try {
            return $this->processStore($request, $lock);
        } finally {
            $lock->release();
        }
    }

    /**
     * Process the store logic (extracted for lock handling)
     */
    protected function processStore(Request $request, $lock)
    {
        if (! $request->has('suspects') && $request->filled('suspect_name')) {
            $request->merge([
                'suspects' => [[
                    'name' => $request->input('suspect_name'),
                    'gender' => $request->input('suspect_gender'),
                    'age' => $request->input('suspect_age'),
                ]],
            ]);
        }

        // Determine investigator type
        $isInvestigator = $request->boolean('is_investigator', true);

        // Build validation rules dynamically
        $rules = [
            'is_investigator' => 'sometimes|boolean',
            // Data Kasus
            'case_number' => 'nullable|string|max:255',
            'letter_date' => 'nullable|date',
            'case_description' => 'nullable|string',
            // File upload
            'request_letter' => 'required|file|mimes:pdf|max:10240',
            'has_expert_witness_request' => 'sometimes|boolean',
            'expert_witness_letter_number' => 'required_if:has_expert_witness_request,1|nullable|string|max:255',
            'expert_witness_letter_date' => 'required_if:has_expert_witness_request,1|nullable|date',
            'expert_witness_request_file' => 'required_if:has_expert_witness_request,1|file|mimes:pdf|max:10240',
            'evidence_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            // Suspect address (single field)
            'suspect_address' => 'nullable|string',
            // Samples
            'samples' => 'required|array|min:1',
            'samples.*.short_description' => 'required|string|max:255',
            'samples.*.type' => 'nullable|string|in:tablet,powder,liquid,plant,other',
            'samples.*.description' => 'nullable|string',
            'samples.*.weight' => 'nullable|numeric|min:0',
            'samples.*.package_quantity' => 'required|integer|min:1',
            'samples.*.unit' => 'required|string|max:50',
            'samples.*.photos' => 'nullable|array',
            'samples.*.photos.*' => 'image|mimes:jpg,jpeg,png|max:5120',
            'samples.*.photo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'samples.*.images' => 'nullable|array',
            'samples.*.images.*' => 'image|mimes:jpg,jpeg,png|max:5120',
        ];

        // Investigator-specific rules
        if ($isInvestigator) {
            $rules['investigator_name'] = 'required|string|min:3|max:255';
            $rules['investigator_nrp'] = 'required|string|max:50';
            $rules['investigator_rank'] = 'required|string';
            $rules['investigator_jurisdiction'] = 'required|string|max:255';
            $rules['investigator_phone'] = 'required|string|max:20';
            $rules['investigator_email'] = 'nullable|email';
            $rules['investigator_address'] = 'nullable|string';
        } else {
            // External (non-Polri) fields
            $rules['external_name'] = 'required|string|min:3|max:255';
            $rules['external_phone'] = 'required|string|max:20';
            $rules['external_institution'] = 'required|string|max:255';
            $rules['external_hp'] = 'required|string|max:20';
            $rules['external_occupation'] = 'required|string|max:255';
        }

        // Suspects array validation (new multi-suspect input)
        $rules['suspects'] = 'sometimes|array|min:1';
        $rules['suspects.*.name'] = 'required|string|max:255';
        $rules['suspects.*.gender'] = 'nullable|in:male,female';
        $rules['suspects.*.age'] = 'nullable|integer|min:0|max:120';

        $messages = [
            'investigator_name.required' => 'Nama penyidik harus diisi',
            'investigator_nrp.required' => 'NRP penyidik harus diisi',
            'investigator_rank.required' => 'Pangkat penyidik harus diisi',
            'investigator_jurisdiction.required' => 'Satuan/wilayah hukum harus diisi',
            'investigator_phone.required' => 'No. HP penyidik harus diisi',
            'external_name.required' => 'Nama harus diisi',
            'external_phone.required' => 'Nomor telepon harus diisi',
            'external_institution.required' => 'Instansi harus diisi',
            'external_hp.required' => 'Nomor HP harus diisi',
            'external_occupation.required' => 'Pekerjaan harus diisi',
            'suspects.required' => 'Minimal 1 tersangka harus diisi',
            'suspects.*.name.required' => 'Nama tersangka harus diisi',
            'request_letter.required' => 'Surat permintaan harus diupload',
            'expert_witness_letter_number.required_if' => 'Nomor surat saksi ahli harus diisi jika permintaan meliputi saksi ahli',
            'expert_witness_letter_date.required_if' => 'Tanggal surat saksi ahli harus diisi jika permintaan meliputi saksi ahli',
            'expert_witness_request_file.required_if' => 'File PDF permintaan saksi ahli harus diupload jika permintaan meliputi saksi ahli',
            'expert_witness_request_file.mimes' => 'File permintaan saksi ahli harus berupa PDF',
            'samples.required' => 'Minimal 1 sampel harus diisi',
            'samples.*.short_description.required' => 'Deskripsi singkat harus diisi',
            'samples.*.package_quantity.required' => 'Jumlah yang diserahkan harus diisi',
            'samples.*.package_quantity.min' => 'Jumlah yang diserahkan minimal 1',
        ];

        $validated = $request->validate($rules, $messages);
        $suspects = $validated['suspects'] ?? [];

        // Initialize variables untuk cleanup di catch block
        $uploadedDocuments = collect([]);

        DB::beginTransaction();

        try {
            // 1. Create or find investigator based on type
            if ($isInvestigator) {
                $investigator = Investigator::updateOrCreate(
                    ['nrp' => $validated['investigator_nrp']],
                    [
                        'is_polri' => true,
                        'name' => $validated['investigator_name'],
                        'rank' => $validated['investigator_rank'],
                        'jurisdiction' => $validated['investigator_jurisdiction'],
                        'phone' => $validated['investigator_phone'],
                        'email' => $validated['investigator_email'] ?? null,
                        'address' => $validated['investigator_address'] ?? null,
                    ]
                );
            } else {
                // Generate synthetic NRP for external user
                $syntheticNrp = 'EXT-'.strtoupper(Str::random(8));

                // Ensure uniqueness
                while (Investigator::where('nrp', $syntheticNrp)->exists()) {
                    $syntheticNrp = 'EXT-'.strtoupper(Str::random(8));
                }

                $investigator = Investigator::create([
                    'is_polri' => false,
                    'nrp' => $syntheticNrp,
                    'name' => $validated['external_name'],
                    'rank' => 'NON-POLRI',
                    'jurisdiction' => $validated['external_institution'],
                    'phone' => $validated['external_hp'],
                    'alt_phone' => $validated['external_phone'],
                    'institution' => $validated['external_institution'],
                    'occupation' => $validated['external_occupation'],
                ]);
            }

            // Ensure folder_key is set
            if (empty($investigator->folder_key)) {
                $investigator->folder_key = trim(($investigator->nrp ? $investigator->nrp.'-' : '').Str::slug($investigator->name ?? 'noname'));
                $investigator->save();
            }

            // 2. Extract first suspect for legacy columns
            $firstSuspect = $suspects[0] ?? null;

            // 3. Create test request
            $testRequest = TestRequest::create([
                'investigator_id' => $investigator->id,
                'user_id' => auth()->id(),
                'case_number' => $validated['case_number'] ?? null,
                'letter_date' => $validated['letter_date'] ?? null,
                'suspect_name' => $firstSuspect['name'] ?? '',
                'suspect_gender' => $firstSuspect['gender'] ?? null,
                'suspect_age' => $firstSuspect['age'] ?? null,
                'suspect_address' => $validated['suspect_address'] ?? null,
                'case_description' => $validated['case_description'] ?? null,
                'has_expert_witness_request' => $request->boolean('has_expert_witness_request'),
                'expert_witness_letter_number' => $request->boolean('has_expert_witness_request')
                    ? ($validated['expert_witness_letter_number'] ?? null)
                    : null,
                'expert_witness_letter_date' => $request->boolean('has_expert_witness_request')
                    ? ($validated['expert_witness_letter_date'] ?? null)
                    : null,
                'official_letter_path' => null,
                'evidence_photo_path' => null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // 4. Create suspect records
            foreach ($suspects as $index => $suspectData) {
                Suspect::create([
                    'test_request_id' => $testRequest->id,
                    'name' => $suspectData['name'],
                    'gender' => $suspectData['gender'] ?? null,
                    'age' => $suspectData['age'] ?? null,
                    'order_no' => $index + 1,
                ]);
            }

            // 5. Upload file surat permintaan via DocumentService
            $documentService = app(DocumentService::class);

            if ($request->hasFile('request_letter')) {
                $letterDoc = $documentService->storeUpload(
                    $request->file('request_letter'),
                    $investigator,
                    $testRequest,
                    'request_letter'
                );
                $uploadedDocuments->push($letterDoc);
                $testRequest->official_letter_path = $letterDoc->path;
            }

            if ($request->boolean('has_expert_witness_request') && $request->hasFile('expert_witness_request_file')) {
                $expertWitnessDoc = $documentService->storeUpload(
                    $request->file('expert_witness_request_file'),
                    $investigator,
                    $testRequest,
                    'expert_witness_request'
                );
                $uploadedDocuments->push($expertWitnessDoc);
            }

            // 6. Upload foto barang bukti (optional) via DocumentService
            if ($request->hasFile('evidence_photo')) {
                $evidenceDoc = $documentService->storeUpload(
                    $request->file('evidence_photo'),
                    $investigator,
                    $testRequest,
                    'evidence_photo'
                );
                $uploadedDocuments->push($evidenceDoc);
                $testRequest->evidence_photo_path = $evidenceDoc->path;
            }

            // Save updated paths
            $testRequest->save();

            // 7. Create samples
            $docs = app(\App\Services\DocumentService::class);

            foreach ($validated['samples'] as $i => $sampleData) {
                $sample = Sample::create([
                    'test_request_id' => $testRequest->id,
                    'short_description' => $sampleData['short_description'],
                    'sample_form' => $sampleData['type'] ?? 'other',
                    'sample_description' => $sampleData['description'] ?? null,
                    'sample_weight' => $sampleData['weight'] ?? null,
                    'package_quantity' => (int) $sampleData['package_quantity'],
                    'unit' => $sampleData['unit'],
                    'test_methods' => null,
                    'requested_test_methods' => null,
                    'active_substance' => null,
                    'condition' => 'baik',
                    'sample_status' => 'received',
                ]);

                // Sample photos handling
                $possibleKeys = [
                    "samples.$i.photos",
                    "samples.$i.photo",
                    "samples.$i.images",
                    "samples.$i.image",
                ];

                $collected = [];
                foreach ($possibleKeys as $key) {
                    if ($request->hasFile($key)) {
                        $files = $request->file($key);
                        $files = is_array($files) ? $files : [$files];
                        foreach ($files as $f) {
                            if ($f && $f->isValid()) {
                                $collected[] = $f;
                            }
                        }
                    }
                }

                foreach ($collected as $photo) {
                    $doc = $docs->storeUpload($photo, $investigator, $testRequest, 'sample_photo');
                    $uploadedDocuments->push($doc);
                    $doc->extra = array_merge($doc->extra ?? [], [
                        'sample_id' => $sample->id,
                        'short_description' => $sample->short_description,
                    ]);
                    $doc->save();
                }
            }

            DB::commit();

            try {
                $visit = \App\Models\GuestVisit::create([
                    'investigator_id' => $investigator->id,
                    'test_request_id' => $testRequest->id,
                    'visit_date' => $validated['letter_date'] ?? now()->toDateString(),
                    'visit_time' => now()->toTimeString(),
                    'purpose' => 'Permohonan Pengujian',
                    'host_id' => auth()->id(),
                    'created_by' => auth()->id(),
                ]);
                $visit->forceFill([
                    'nda_accepted' => true,
                    'nda_accepted_at' => now(),
                ])->save();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create guest visit on request store', [
                    'test_request_id' => $testRequest->id,
                    'error' => $e->getMessage(),
                ]);
                session()->flash('warning', 'Permintaan berhasil dibuat, tetapi pencatatan buku tamu gagal. Silakan catat manual.');
            }

            $driveSync = app(GoogleDriveDocumentSyncService::class)
                ->syncUploadedDocuments($uploadedDocuments, $request->user());

            $successMessage = 'Permintaan pengujian berhasil dibuat dengan nomor: '.$testRequest->request_number;
            if ($driveSync['uploaded'] > 0) {
                $successMessage .= " {$driveSync['uploaded']} file berhasil disimpan ke Google Drive.";
            } elseif ($uploadedDocuments->isNotEmpty()) {
                $successMessage .= ' File lokal tersimpan, tetapi Google Drive belum tersinkronisasi. Pastikan akun Google Drive terhubung.';
            }

            if ($submissionToken = $request->input('_submission_token')) {
                Cache::put('submission_token_'.$submissionToken, true, 300);
            }

            return redirect()->route('requests.show', $testRequest->id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollback();

            // Cleanup uploaded files on error
            foreach ($uploadedDocuments as $doc) {
                if ($doc && $doc->path) {
                    $disk = $doc->storage_disk ?? 'public';
                    if (Storage::disk($disk)->exists($doc->path)) {
                        Storage::disk($disk)->delete($doc->path);
                    }
                }
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Terjadi kesalahan: '.$e->getMessage(),
                ], 500);
            }

            return back()->withInput()
                ->withErrors(['error' => 'Terjadi kesalahan: '.$e->getMessage()]);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

        $request = TestRequest::with(['investigator', 'samples'])
            ->findOrFail($id);

        return view('requests.show', compact('request'));

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $request = TestRequest::with(['investigator', 'samples', 'documents', 'suspects'])->findOrFail($id);

        return view('requests.edit', compact('request'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Double-submit prevention using submission token
        $submissionToken = $request->input('_submission_token');
        if ($submissionToken) {
            $cacheKey = 'submission_token_'.$submissionToken;

            // Check if this token was already used
            if (Cache::has($cacheKey)) {
                Log::warning('Duplicate update submission detected', [
                    'token' => $submissionToken,
                    'request_id' => $id,
                    'user_id' => auth()->id(),
                ]);

                return redirect()->route('requests.show', $id)
                    ->with('warning', 'Perubahan ini sudah diproses sebelumnya.');
            }

        }

        $testRequest = TestRequest::with(['investigator', 'suspects'])->findOrFail($id);

        // Determine investigator type
        $isInvestigator = $request->boolean('is_investigator', $testRequest->investigator->is_polri ?? true);
        $hasExistingExpertWitnessDocument = $testRequest->documents()
            ->where('document_type', 'expert_witness_request')
            ->exists();
        $expertWitnessUploadRule = $request->boolean('has_expert_witness_request') && ! $hasExistingExpertWitnessDocument
            ? 'required|file|mimes:pdf|max:10240'
            : 'nullable|file|mimes:pdf|max:10240';

        // Build validation rules dynamically
        $rules = [
            'case_number' => 'nullable|string|max:255',
            'letter_date' => 'nullable|date',
            'suspect_address' => 'nullable|string',
            // Samples
            'samples' => 'required|array|min:1',
            'samples.*.id' => 'nullable|exists:samples,id,test_request_id,'.$testRequest->id,
            'samples.*.short_description' => 'required|string|max:255',
            'samples.*.package_quantity' => 'required|integer|min:1',
            'samples.*.unit' => 'required|string|max:50',
            // Suspects array
            'suspects' => 'required|array|min:1',
            'suspects.*.name' => 'required|string|max:255',
            'suspects.*.gender' => 'nullable|in:male,female',
            'suspects.*.age' => 'nullable|integer|min:0|max:120',
            // File uploads
            'request_letter' => 'nullable|file|mimes:pdf|max:10240',
            'has_expert_witness_request' => 'sometimes|boolean',
            'expert_witness_letter_number' => 'required_if:has_expert_witness_request,1|nullable|string|max:255',
            'expert_witness_letter_date' => 'required_if:has_expert_witness_request,1|nullable|date',
            'expert_witness_request_file' => $expertWitnessUploadRule,
            'evidence_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ];

        // Investigator-specific rules
        if ($isInvestigator) {
            $rules['investigator_rank'] = 'required|string|max:255';
            $rules['investigator_name'] = 'required|string|max:255';
            $rules['investigator_nrp'] = 'required|string|max:50';
            $rules['investigator_jurisdiction'] = 'required|string|max:255';
            $rules['investigator_phone'] = 'required|string|max:20';
        } else {
            $rules['external_name'] = 'required|string|min:3|max:255';
            $rules['external_phone'] = 'required|string|max:20';
            $rules['external_institution'] = 'required|string|max:255';
            $rules['external_hp'] = 'required|string|max:20';
            $rules['external_occupation'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        $uploadedDocuments = collect([]);
        $documentsToDeleteAfterCommit = collect([]);
        $pathsToDeleteAfterCommit = collect([]);

        DB::beginTransaction();

        try {
            // Update investigator based on type
            $inv = $testRequest->investigator;

            if ($isInvestigator) {
                $inv->update([
                    'is_polri' => true,
                    'rank' => $validated['investigator_rank'],
                    'name' => $validated['investigator_name'],
                    'nrp' => $validated['investigator_nrp'],
                    'jurisdiction' => $validated['investigator_jurisdiction'],
                    'phone' => $validated['investigator_phone'],
                ]);
            } else {
                $inv->update([
                    'is_polri' => false,
                    'name' => $validated['external_name'],
                    'rank' => 'NON-POLRI',
                    'jurisdiction' => $validated['external_institution'],
                    'phone' => $validated['external_hp'],
                    'alt_phone' => $validated['external_phone'],
                    'institution' => $validated['external_institution'],
                    'occupation' => $validated['external_occupation'],
                ]);
            }

            // Extract first suspect for legacy columns
            $firstSuspect = $validated['suspects'][0] ?? null;

            // Update test request
            $updateData = [
                'case_number' => $validated['case_number'],
                'letter_date' => $validated['letter_date'] ?? null,
                'suspect_name' => $firstSuspect['name'] ?? '',
                'suspect_gender' => $firstSuspect['gender'] ?? null,
                'suspect_age' => $firstSuspect['age'] ?? null,
                'suspect_address' => $validated['suspect_address'] ?? null,
                'has_expert_witness_request' => $request->boolean('has_expert_witness_request'),
                'expert_witness_letter_number' => $request->boolean('has_expert_witness_request')
                    ? ($validated['expert_witness_letter_number'] ?? null)
                    : null,
                'expert_witness_letter_date' => $request->boolean('has_expert_witness_request')
                    ? ($validated['expert_witness_letter_date'] ?? null)
                    : null,
            ];

            // Handle File Uploads
            $documentService = app(DocumentService::class);

            // 1. Surat Permintaan
            if ($request->hasFile('request_letter')) {
                $existingRequestLetterDocuments = $testRequest->documents()
                    ->where('document_type', 'request_letter')
                    ->get();
                $documentsToDeleteAfterCommit = $documentsToDeleteAfterCommit->merge($existingRequestLetterDocuments);

                if ($testRequest->official_letter_path && $existingRequestLetterDocuments->doesntContain(fn (Document $document) => ($document->file_path ?? $document->path) === $testRequest->official_letter_path)) {
                    $pathsToDeleteAfterCommit->push(['disk' => 'public', 'path' => $testRequest->official_letter_path]);
                }

                $letterDoc = $documentService->storeUpload(
                    $request->file('request_letter'),
                    $inv,
                    $testRequest,
                    'request_letter'
                );
                $uploadedDocuments->push($letterDoc);
                $updateData['official_letter_path'] = $letterDoc->path;
            }

            if ($request->boolean('has_expert_witness_request')) {
                if ($request->hasFile('expert_witness_request_file')) {
                    $existingExpertWitnessDocuments = $testRequest->documents()
                        ->where('document_type', 'expert_witness_request')
                        ->get();
                    $documentsToDeleteAfterCommit = $documentsToDeleteAfterCommit->merge($existingExpertWitnessDocuments);

                    $expertWitnessDoc = $documentService->storeUpload(
                        $request->file('expert_witness_request_file'),
                        $inv,
                        $testRequest,
                        'expert_witness_request'
                    );
                    $uploadedDocuments->push($expertWitnessDoc);
                }
            } else {
                $existingExpertWitnessDocuments = $testRequest->documents()
                    ->where('document_type', 'expert_witness_request')
                    ->get();
                $documentsToDeleteAfterCommit = $documentsToDeleteAfterCommit->merge($existingExpertWitnessDocuments);
            }

            // 2. Foto Barang Bukti
            if ($request->hasFile('evidence_photo')) {
                $existingEvidencePhotoDocuments = $testRequest->documents()
                    ->where('document_type', 'evidence_photo')
                    ->get();
                $documentsToDeleteAfterCommit = $documentsToDeleteAfterCommit->merge($existingEvidencePhotoDocuments);

                if ($testRequest->evidence_photo_path && $existingEvidencePhotoDocuments->doesntContain(fn (Document $document) => ($document->file_path ?? $document->path) === $testRequest->evidence_photo_path)) {
                    $pathsToDeleteAfterCommit->push(['disk' => 'public', 'path' => $testRequest->evidence_photo_path]);
                }

                $evidenceDoc = $documentService->storeUpload(
                    $request->file('evidence_photo'),
                    $inv,
                    $testRequest,
                    'evidence_photo'
                );
                $uploadedDocuments->push($evidenceDoc);
                $updateData['evidence_photo_path'] = $evidenceDoc->path;
            }

            $testRequest->update($updateData);

            // Replace all suspects (delete old, insert new)
            Suspect::where('test_request_id', $testRequest->id)->delete();
            foreach ($validated['suspects'] as $index => $suspectData) {
                Suspect::create([
                    'test_request_id' => $testRequest->id,
                    'name' => $suspectData['name'],
                    'gender' => $suspectData['gender'] ?? null,
                    'age' => $suspectData['age'] ?? null,
                    'order_no' => $index + 1,
                ]);
            }

            // Update samples
            $submittedSampleIds = [];

            foreach ($validated['samples'] as $sampleData) {
                if (! empty($sampleData['id'])) {
                    $sample = Sample::find($sampleData['id']);
                    if ($sample && $sample->test_request_id == $testRequest->id) {
                        $sample->update([
                            'short_description' => $sampleData['short_description'],
                            'package_quantity' => $sampleData['package_quantity'],
                            'unit' => $sampleData['unit'],
                        ]);
                        $submittedSampleIds[] = $sample->id;
                    }
                } else {
                    $newSample = Sample::create([
                        'test_request_id' => $testRequest->id,
                        'short_description' => $sampleData['short_description'],
                        'active_substance' => null,
                        'package_quantity' => $sampleData['package_quantity'],
                        'unit' => $sampleData['unit'],
                        'sample_form' => 'other',
                        'test_methods' => null,
                        'requested_test_methods' => null,
                        'condition' => 'baik',
                        'sample_status' => 'received',
                    ]);
                    $submittedSampleIds[] = $newSample->id;
                }
            }

            // Delete samples that were removed
            $removedSamples = Sample::where('test_request_id', $testRequest->id)
                ->whereNotIn('id', $submittedSampleIds)
                ->get();

            foreach ($removedSamples as $removedSample) {
                // Ensure deleted event can access testRequest without extra queries
                $removedSample->setRelation('testRequest', $testRequest);
                $removedSample->delete();
            }

            // After deletions: compact sample_code numbering (skip locked samples)
            if ($removedSamples->isNotEmpty()) {
                $anchor = $removedSamples->min('created_at');
                $bucketNow = $anchor
                    ? \Carbon\CarbonImmutable::parse($anchor)
                    : \Carbon\CarbonImmutable::now();

                app(NumberingRepairService::class)->compactSampleCodesForBucket($bucketNow);
            }

            // DELETE old Berita Acara documents (force re-generation)
            $baDocuments = Document::where('test_request_id', $testRequest->id)
                ->whereIn('document_type', ['ba_penerimaan', 'ba_penerimaan_html'])
                ->get();

            foreach ($baDocuments as $baDoc) {
                $documentsToDeleteAfterCommit->push($baDoc);

                Log::info('Deleted old BA document after edit', [
                    'request_id' => $testRequest->id,
                    'document_id' => $baDoc->id,
                    'document_type' => $baDoc->document_type,
                ]);
            }

            // Also delete legacy HTML file if exists
            $baFilename = "Berita_Acara_Penerimaan_{$testRequest->request_number}_ID-{$testRequest->id}.html";
            $baFilepath = base_path("output/{$baFilename}");

            if (file_exists($baFilepath)) {
                @unlink($baFilepath);
            }

            DB::commit();

            $this->deleteDocumentsAfterCommit($documentsToDeleteAfterCommit, $request->user());
            $this->deleteStoredPathsAfterCommit($pathsToDeleteAfterCommit);

            $driveSync = app(GoogleDriveDocumentSyncService::class)
                ->syncUploadedDocuments($uploadedDocuments, $request->user());

            $successMessage = 'Permintaan berhasil diupdate! Silakan generate ulang Berita Acara dengan data terbaru.';
            if ($driveSync['uploaded'] > 0) {
                $successMessage .= " {$driveSync['uploaded']} file baru berhasil disimpan ke Google Drive.";
            } elseif ($uploadedDocuments->isNotEmpty()) {
                $successMessage .= ' File lokal tersimpan, tetapi Google Drive belum tersinkronisasi. Pastikan akun Google Drive terhubung.';
            }

            if ($submissionToken = $request->input('_submission_token')) {
                Cache::put('submission_token_'.$submissionToken, true, 300);
            }

            return redirect()->route('requests.show', $id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Terjadi kesalahan: '.$e->getMessage(),
                ], 500);
            }

            try {
                Log::error('Error updating request', [
                    'request_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable) {
                // Preserve the user-facing validation/redirect flow even if logging is misconfigured.
            }

            return back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function updateVerifiedAt(Request $request, TestRequest $testRequest)
    {
        $validated = $request->validate([
            'verified_at' => 'required|date',
        ], [
            'verified_at.required' => 'Tanggal verifikasi harus diisi',
            'verified_at.date' => 'Format tanggal tidak valid',
        ]);

        $testRequest->update([
            'verified_at' => $validated['verified_at'],
        ]);

        return back()->with('success', 'Tanggal verifikasi Urmin berhasil disimpan.');
    }

    private function deleteDocumentsAfterCommit($documents, ?User $user): void
    {
        $documents->unique('id')->each(function (Document $document) use ($user) {
            try {
                $driveDeleted = app(GoogleDriveDocumentSyncService::class)->deleteSyncedDocument($document, $user);

                $path = $document->file_path ?? $document->path;
                $disk = $document->storage_disk ?? 'public';
                if ($path && Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }

                $driveDeleted ? $document->forceDelete() : $document->delete();
            } catch (\Throwable $exception) {
                Log::warning('Deferred document cleanup failed', [
                    'document_id' => $document->id,
                    'document_type' => $document->document_type,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function deleteStoredPathsAfterCommit($paths): void
    {
        $paths->unique(fn (array $item) => ($item['disk'] ?? 'public').'|'.($item['path'] ?? ''))->each(function (array $item) {
            try {
                $disk = $item['disk'] ?? 'public';
                $path = $item['path'] ?? null;

                if ($path && Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Throwable $exception) {
                Log::warning('Deferred file cleanup failed', [
                    'disk' => $item['disk'] ?? null,
                    'path' => $item['path'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function downloadDocument(TestRequest $testRequest, string $type)
    {
        $user = auth()->user();
        $isRequestOwner = $user && (int) $testRequest->user_id === (int) $user->id;
        $isAdminRole = $user && in_array($user->role, ['admin', 'admin-lpmf'], true);

        if (! $isRequestOwner && ! $isAdminRole && ! $user?->can('permintaan.view')) {
            abort(403);
        }

        $allowedTypes = [
            'request_letter',
            'expert_witness_request',
            'evidence_photo',
            'sample_photo',
            'sample_receipt',
            'handover_report',
            'request_letter_receipt',
            'ba_penerimaan',
            'ba_penerimaan_html',
        ];

        if (! in_array($type, $allowedTypes, true)) {
            abort(404);
        }

        $document = $testRequest->documents()->where('document_type', $type)->latest()->firstOrFail();

        request()->attributes->set('audit_subject', $document);
        request()->attributes->set('audit_meta', [
            'document_type' => $document->document_type,
            'original_filename' => $document->original_filename,
            'request_number' => $testRequest->request_number,
        ]);

        $path = $document->file_path ?? $document->path;
        $disk = $document->storage_disk ?? 'documents';

        if (! $path || ! Storage::disk($disk)->exists($path)) {

            abort(404, 'Dokumen tidak ditemukan di penyimpanan.');

        }

        return Storage::disk($disk)->download($path, $document->original_filename);

    }

    public function deleteDocument(TestRequest $testRequest, string $type)
    {
        $user = auth()->user();
        $isRequestOwner = $user && (int) $testRequest->user_id === (int) $user->id;
        $isAdminRole = $user && in_array($user->role, ['admin', 'admin-lpmf'], true);

        if (! $isRequestOwner && ! $isAdminRole && ! $user?->can('permintaan.create')) {
            return response()->json([
                'ok' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus dokumen ini.',
            ], 403);
        }

        // Validasi tipe dokumen yang diizinkan

        $allowedTypes = ['sample_receipt', 'handover_report', 'request_letter_receipt'];

        if (! in_array($type, $allowedTypes, true)) {

            return response()->json([

                'ok' => false,

                'message' => 'Tipe dokumen tidak valid.',

            ], 422);

        }

        // Cek otorisasi - hanya user yang membuat request atau admin yang bisa hapus

        // Untuk sekarang, semua authenticated user bisa hapus (bisa disesuaikan dengan policy)

        if (auth()->guest()) {

            return response()->json([

                'ok' => false,

                'message' => 'Anda tidak memiliki akses untuk menghapus dokumen ini.',

            ], 403);

        }

        // Cari dokumen berdasarkan type

        $document = $testRequest->documents()
            ->where('document_type', $type)
            ->orderByDesc('id')
            ->first();

        if (! $document) {

            return response()->json([

                'ok' => false,

                'message' => 'Dokumen tidak ditemukan.',

            ], 404);

        }

        try {

            // Hapus file dari storage jika ada

            $path = $document->file_path ?? $document->path;
            $disk = $document->storage_disk ?? 'documents';
            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }

            $driveDeleted = app(GoogleDriveDocumentSyncService::class)->deleteSyncedDocument($document, auth()->user());

            // Simpan info untuk audit log

            $documentInfo = [

                'type' => $document->document_type,

                'filename' => $document->original_filename,

                'request_number' => $testRequest->request_number,

            ];

            // Hapus record dari database

            $driveDeleted ? $document->forceDelete() : $document->delete();

            // Log audit

            \Log::info('Document deleted', [

                'user_id' => auth()->id(),

                'user_name' => auth()->user()->name ?? 'Unknown',

                'request_id' => $testRequest->id,

                'request_number' => $testRequest->request_number,

                'document_type' => $documentInfo['type'],

                'document_filename' => $documentInfo['filename'],

                'deleted_at' => now()->toDateTimeString(),

            ]);

            return response()->json([

                'ok' => true,

                'requestId' => $testRequest->id,

                'removed' => $type,

                'message' => 'Dokumen berhasil dihapus.',

            ], 200);

        } catch (\Exception $e) {

            \Log::error('Failed to delete document', [

                'user_id' => auth()->id(),

                'request_id' => $testRequest->id,

                'document_type' => $type,

                'error' => $e->getMessage(),

            ]);

            return response()->json([

                'ok' => false,

                'message' => 'Terjadi kesalahan saat menghapus dokumen: '.$e->getMessage(),

            ], 500);

        }

    }

    public function destroy(string $id)
    {
        $testRequest = TestRequest::findOrFail($id);
        $anchor = \Carbon\CarbonImmutable::parse($testRequest->created_at);

        DB::transaction(function () use ($testRequest) {
            // Hapus dokumen terkait terlebih dahulu
            foreach ($testRequest->documents as $doc) {
                $path = $doc->file_path ?? $doc->path;
                if ($path) {
                    $disk = $doc->storage_disk ?? 'public';
                    Storage::disk($disk)->delete($path);
                }

                $doc->forceDelete();
            }

            // Hapus sampel terkait
            foreach ($testRequest->samples as $sample) {
                if ($sample->photo_path) {
                    Storage::disk('samples')->delete($sample->photo_path);
                }
                if ($sample->receipt_path) {
                    Storage::disk('public')->delete($sample->receipt_path);
                }
                $sample->delete();
            }

            // Hapus survey responses terkait
            $testRequest->surveyResponses()->delete();

            // Hapus survey pelanggan terkait
            $testRequest->customerSurvey()->delete();

            // Hapus file terkait
            if ($testRequest->official_letter_path) {
                Storage::disk('documents')->delete($testRequest->official_letter_path);
            }

            if ($testRequest->evidence_photo_path) {
                Storage::disk('samples')->delete($testRequest->evidence_photo_path);
            }

            // Hapus test request
            $testRequest->delete();
        });

        // Auto-compact BA/Tracking numbers (Post-commit)
        try {
            $repairService = app(NumberingRepairService::class);
            $result = $repairService->compactRequestNumbersForBucket(
                $anchor,
                'Auto compact after request deletion'
            );

            if (! empty($result['fs_ops'])) {
                $repairService->executePostCommitFilesystemOps($result['fs_ops']);
            }
        } catch (\Throwable $e) {
            Log::warning('Post-delete BA/tracking compaction failed', [
                'error' => $e->getMessage(),
                'request_id' => $id,
            ]);
        }

        return redirect()->route('requests.index')
            ->with('success', 'Permintaan berhasil dihapus!');
    }

    private function generateRequestReceipts(TestRequest $testRequest): void
    {
        // Extend execution time for PDF generation (3 PDFs can take 30-60 seconds)
        set_time_limit(120);

        $testRequest->loadMissing(['investigator', 'samples']);

        $methodLabels = $this->getTestMethodLabels();

        $generatedAt = now();

        $baseName = Str::slug($testRequest->request_number, '-');

        $userId = auth()->id();

        // Sample receipt

        $samplePdf = Pdf::loadView('pdf.sample-receipt', [

            'request' => $testRequest,

            'methodLabels' => $methodLabels,

            'generatedAt' => $generatedAt,

        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96); // Lower DPI for faster rendering

        $sampleContent = $samplePdf->output();

        $samplePath = "receipts/sample/{$baseName}-tanda-terima-sampel.pdf";

        Storage::disk('documents')->put($samplePath, $sampleContent);

        Document::create([

            'test_request_id' => $testRequest->id,

            'document_type' => 'sample_receipt',

            'source' => 'generated',

            'storage_disk' => 'documents',

            'file_path' => $samplePath,

            'original_filename' => 'Tanda Terima Sampel '.$testRequest->request_number.'.pdf',

            'file_size' => strlen($sampleContent),

            'mime_type' => 'application/pdf',

            'generated_by' => $userId,

        ]);

        // Request letter receipt

        $letterPdf = Pdf::loadView('pdf.request-letter-receipt', [

            'request' => $testRequest,

            'methodLabels' => $methodLabels,

            'generatedAt' => $generatedAt,

        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96);

        $letterContent = $letterPdf->output();

        $letterPath = "receipts/letter/{$baseName}-tanda-terima-surat.pdf";

        Storage::disk('documents')->put($letterPath, $letterContent);

        Document::create([

            'test_request_id' => $testRequest->id,

            'document_type' => 'request_letter_receipt',

            'source' => 'generated',

            'storage_disk' => 'documents',

            'file_path' => $letterPath,

            'original_filename' => 'Tanda Terima Surat '.$testRequest->request_number.'.pdf',

            'file_size' => strlen($letterContent),

            'mime_type' => 'application/pdf',

            'generated_by' => $userId,

        ]);

        // Handover / berita acara

        $handoverPdf = Pdf::loadView('pdf.handover-report', [

            'request' => $testRequest,

            'generatedAt' => $generatedAt,

        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96);

        $handoverContent = $handoverPdf->output();

        $handoverPath = "receipts/handover/{$baseName}-berita-acara.pdf";

        Storage::disk('documents')->put($handoverPath, $handoverContent);

        Document::create([

            'test_request_id' => $testRequest->id,

            'document_type' => 'handover_report',

            'source' => 'generated',

            'storage_disk' => 'documents',

            'file_path' => $handoverPath,

            'original_filename' => 'Berita Acara '.$testRequest->request_number.'.pdf',

            'file_size' => strlen($handoverContent),

            'mime_type' => 'application/pdf',

            'generated_by' => $userId,

        ]);

    }

    private function getTestMethodLabels(): array
    {

        return [

            'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',

            'gc_ms' => 'Identifikasi GC-MS',

            'lc_ms' => 'Identifikasi LC-MS',

        ];

    }

    /**
     * Check if Berita Acara exists for this request
     */
    public function checkBeritaAcara(TestRequest $testRequest)
    {
        // Check if document exists in documents table
        $document = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'ba_penerimaan')
            ->where('source', 'generated')
            ->first();

        $documentService = app(DocumentService::class);
        $exists = $document !== null && $documentService->fileExists($document);

        return response()->json([
            'exists' => $exists,
            'filename' => $document->filename ?? null,
            'document_id' => $document->id ?? null,
            'request_id' => $testRequest->id,
        ]);
    }

    /**
     * Generate Berita Acara Penerimaan
     */
    public function generateBeritaAcara(TestRequest $testRequest)
    {
        try {
            $documentService = app(DocumentService::class);
            $templateService = app(\App\Services\DocumentTemplateService::class);
            $pdfRenderService = app(\App\Services\PdfRenderService::class);

            $currentSignerId = auth()->id();
            if ($currentSignerId !== null) {
                if ((int) $testRequest->user_id !== (int) $currentSignerId) {
                    $testRequest->forceFill(['user_id' => $currentSignerId])->save();
                }
                $testRequest->setRelation('user', auth()->user());
            }

            // Ambil relasi lengkap
            $testRequest->loadMissing(['investigator', 'samples', 'user']);
            $inv = $testRequest->investigator;

            // Validate investigator exists
            if (! $inv) {
                return back()->with('error', 'Investigator tidak ditemukan untuk test request ini.');
            }

            // Pastikan folder_key ada (fallback kalau model belum auto-set)
            if (empty($inv->folder_key)) {
                $inv->folder_key = trim(($inv->nrp ? $inv->nrp.'-' : '').\Illuminate\Support\Str::slug($inv->name ?? 'noname'));
                $inv->save();
            }

            // Use the existing request_number as the BA document number
            // DO NOT call numberingService->issue() here - it would create a new sequence number
            // The request_number was already generated when TestRequest was created
            $baNumber = $testRequest->request_number;

            // Generate filesystem-safe baseName from document number
            $baseName = $documentService->generateDocumentBaseName('ba_penerimaan', $baNumber);

            // Try to get active template for BA
            $template = $templateService->getActiveTemplateByDocType('BA');

            $templateId = null;
            $templateVersion = null;
            $templateHash = null;
            $html = null;

            if ($template) {
                // Use template system
                Log::info('Using active template for BA generation', [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'request_id' => $testRequest->id,
                ]);

                // Prepare data for template rendering
                $data = [
                    'request_number' => $testRequest->request_number,
                    'case_number' => $testRequest->case_number,
                    'expert_witness_letter_number' => $testRequest->expert_witness_letter_number,
                    'expert_witness_letter_date' => $testRequest->expert_witness_letter_date?->translatedFormat('d F Y'),
                    'generated_at' => now()->format('d F Y'),
                    'investigator_name' => $inv->name,
                    'investigator_nrp' => $inv->nrp,
                    'investigator_rank' => $inv->rank,
                    'investigator_jurisdiction' => $inv->jurisdiction,
                    'investigator_phone' => $inv->phone,
                    'suspect_name' => $testRequest->suspect_name,
                    'suspect_gender' => $testRequest->suspect_gender,
                    'suspect_age' => $testRequest->suspect_age,
                    'suspect_address' => $testRequest->suspect_address,
                    'sample_count' => $testRequest->samples->count(),
                    'lab_name' => 'Pusdokkes Polri',
                    'lab_address' => 'Jakarta',
                ];

                // Render HTML from template
                $html = $templateService->renderHtmlFromTemplate($template, $data);

                // Track template metadata
                $templateId = $template->id;
                $templateVersion = $template->version;
                $templateHash = $templateService->calculateTemplateHash($template);
            } else {
                // Fallback to legacy view
                Log::warning('No active template found for BA, using legacy view', [
                    'request_id' => $testRequest->id,
                ]);

                $renderService = app(DocumentRenderService::class);
                $rendered = $renderService->render(
                    type: DocumentType::BA_PENERIMAAN,
                    contextId: $testRequest->id
                );

                // Return immediately with legacy system - use replaceExisting to avoid duplicates
                $doc = $documentService->storeGenerated(
                    binary: $rendered->content,
                    ext: 'pdf',
                    inv: $inv,
                    req: $testRequest,
                    type: 'ba_penerimaan',
                    baseName: $baseName,
                    replaceExisting: true,
                    syncUser: request()->user()
                );

                if (request()->boolean('download')) {
                    return $rendered->toDownloadResponse();
                }

                return $rendered->toInlineResponse();
            }

            // Generate PDF from HTML using PdfRenderService
            $pdfBinary = $pdfRenderService->htmlToPdf($html, config('app.url'));

            // Save PDF via DocumentService - use replaceExisting to avoid duplicates
            $doc = $documentService->storeGenerated(
                binary: $pdfBinary,
                ext: 'pdf',
                inv: $inv,
                req: $testRequest,
                type: 'ba_penerimaan',
                baseName: $baseName,
                replaceExisting: true,
                syncUser: request()->user()
            );

            // Update document metadata dengan template info
            if ($doc && $templateId) {
                $extra = $doc->extra ?? [];
                $extra['template_id'] = $templateId;
                $extra['template_version'] = $templateVersion;
                $extra['template_hash'] = $templateHash;
                $doc->extra = $extra;
                $doc->save();

                Log::info('BA generated with template metadata', [
                    'document_id' => $doc->id,
                    'template_id' => $templateId,
                    'template_version' => $templateVersion,
                    'request_id' => $testRequest->id,
                ]);
            }

            // Download atau inline view
            if (request()->boolean('download')) {
                return response()->download(
                    storage_path('app/public/'.$doc->path),
                    $doc->filename,
                    ['Content-Type' => 'application/pdf']
                );
            }

            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$doc->filename.'"',
            ]);

        } catch (\Throwable $e) {
            \Log::error('Exception in generateBeritaAcara', [
                'request_id' => $testRequest->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback aman: kembali dengan error flash
            return back()->with('error', 'Gagal membuat Berita Acara: '.$e->getMessage());
        }
    }

    /**
     * Download Berita Acara Penerimaan
     */
    public function downloadBeritaAcara(TestRequest $testRequest)
    {
        $documentService = app(DocumentService::class);
        // Find the PDF document
        $document = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'ba_penerimaan')
            ->where('source', 'generated')
            ->latest()
            ->first();

        if (! $document || ! $documentService->fileExists($document)) {
            return back()->with('error', 'Berita Acara belum di-generate. Silakan generate terlebih dahulu.');
        }

        $filePath = $documentService->getFilePath($document);

        return response()->download($filePath, $document->filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * View Berita Acara Penerimaan in browser
     */
    public function viewBeritaAcara(TestRequest $testRequest)
    {
        $documentService = app(DocumentService::class);
        // Find the HTML document first, fallback to PDF
        $htmlDocument = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'ba_penerimaan_html')
            ->where('source', 'generated')
            ->latest()
            ->first();

        if ($htmlDocument && $documentService->fileExists($htmlDocument)) {
            $filePath = $documentService->getFilePath($htmlDocument);

            return response()->file($filePath, [
                'Content-Type' => 'text/html',
            ]);
        }

        // Fallback to PDF if HTML not available
        $pdfDocument = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'ba_penerimaan')
            ->where('source', 'generated')
            ->latest()
            ->first();

        if ($pdfDocument && $documentService->fileExists($pdfDocument)) {
            $filePath = $documentService->getFilePath($pdfDocument);

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return back()->with('error', 'Berita Acara belum di-generate. Silakan generate terlebih dahulu.');
    }
}
