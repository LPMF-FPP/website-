<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\Suspect;
use App\Models\TestRequest;
use App\Services\ActiveSubstanceService;
use App\Services\DocumentGeneration\DocumentRenderService;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            $cacheKey = 'submission_token_' . $submissionToken;
            
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
            
            // Mark token as used immediately (expires after 5 minutes)
            Cache::put($cacheKey, true, 300);
        }

        // Additional lock based on user + timestamp to prevent race conditions
        $lockKey = 'request_store_user_' . auth()->id();
        $lock = Cache::lock($lockKey, 10); // 10 second lock
        
        if (!$lock->get()) {
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
            'case_description' => 'nullable|string',
            'to_office' => 'required|string|max:255',
            // File upload
            'request_letter' => 'required|file|mimes:pdf|max:10240',
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
            'samples.*.test_types' => 'required|array|min:1',
            'samples.*.test_types.*' => 'in:uv_vis,gc_ms,lc_ms',
            'samples.*.active_substance' => 'required|string|max:255',
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
            'samples.required' => 'Minimal 1 sampel harus diisi',
            'samples.*.short_description.required' => 'Deskripsi singkat harus diisi',
            'samples.*.test_types.required' => 'Pilih minimal satu jenis pengujian',
            'samples.*.test_types.*.in' => 'Jenis pengujian tidak valid',
            'samples.*.active_substance.required' => 'Zat aktif harus diisi',
            'samples.*.package_quantity.required' => 'Jumlah yang diserahkan harus diisi',
            'samples.*.package_quantity.min' => 'Jumlah yang diserahkan minimal 1',
        ];

        $validated = $request->validate($rules, $messages);
        $suspects = $validated['suspects'] ?? [];

        \Log::info('FILES KEYS', ['keys' => array_keys(Arr::dot($request->allFiles()))]);

        // Initialize variables untuk cleanup di catch block
        $letterDoc = null;
        $evidenceDoc = null;

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
                'to_office' => $validated['to_office'],
                'case_number' => $validated['case_number'] ?? null,
                'suspect_name' => $firstSuspect['name'] ?? '',
                'suspect_gender' => $firstSuspect['gender'] ?? null,
                'suspect_age' => $firstSuspect['age'] ?? null,
                'suspect_address' => $validated['suspect_address'] ?? null,
                'case_description' => $validated['case_description'] ?? null,
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
                $testRequest->official_letter_path = $letterDoc->path;
            }

            // 6. Upload foto barang bukti (optional) via DocumentService
            if ($request->hasFile('evidence_photo')) {
                $evidenceDoc = $documentService->storeUpload(
                    $request->file('evidence_photo'),
                    $investigator,
                    $testRequest,
                    'evidence_photo'
                );
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
                    'test_methods' => json_encode(array_values($sampleData['test_types'])),
                    'requested_test_methods' => json_encode(array_values($sampleData['test_types'])),
                    'active_substance' => $sampleData['active_substance'],
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
                    $doc->extra = array_merge($doc->extra ?? [], [
                        'sample_id' => $sample->id,
                        'short_description' => $sample->short_description,
                    ]);
                    $doc->save();
                }
            }

            DB::commit();

            return redirect()->route('requests.show', $testRequest->id)
                ->with('success', 'Permintaan pengujian berhasil dibuat dengan nomor: '.$testRequest->request_number);

        } catch (\Exception $e) {
            DB::rollback();

            // Cleanup uploaded files on error
            if ($letterDoc && $letterDoc->path) {
                $disk = $letterDoc->storage_disk ?? 'public';
                Storage::disk($disk)->delete($letterDoc->path);
            }

            if ($evidenceDoc && $evidenceDoc->path) {
                $disk = $evidenceDoc->storage_disk ?? 'public';
                Storage::disk($disk)->delete($evidenceDoc->path);
            }

            if (isset($testRequest) && $testRequest->id) {
                $samplePhotoDocs = Document::where('test_request_id', $testRequest->id)
                    ->where('document_type', 'sample_photo')
                    ->get();

                foreach ($samplePhotoDocs as $doc) {
                    if ($doc->path) {
                        $disk = $doc->storage_disk ?? 'public';
                        Storage::disk($disk)->delete($doc->path);
                    }
                }
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
            $cacheKey = 'submission_token_' . $submissionToken;
            
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
            
            // Mark token as used immediately (expires after 5 minutes)
            Cache::put($cacheKey, true, 300);
        }

        $testRequest = TestRequest::with(['investigator', 'suspects'])->findOrFail($id);

        // Determine investigator type
        $isInvestigator = $request->boolean('is_investigator', $testRequest->investigator->is_polri ?? true);

        // Build validation rules dynamically
        $rules = [
            'case_number' => 'nullable|string|max:255',
            'to_office' => 'required|string|max:255',
            'suspect_address' => 'nullable|string',
            // Samples
            'samples' => 'required|array|min:1',
            'samples.*.id' => 'nullable|exists:samples,id',
            'samples.*.short_description' => 'required|string|max:255',
            'samples.*.active_substance' => 'required|string|max:255',
            'samples.*.package_quantity' => 'required|numeric|min:0',
            'samples.*.unit' => 'required|string|max:50',
            // Suspects array
            'suspects' => 'required|array|min:1',
            'suspects.*.name' => 'required|string|max:255',
            'suspects.*.gender' => 'nullable|in:male,female',
            'suspects.*.age' => 'nullable|integer|min:0|max:120',
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
            $testRequest->update([
                'case_number' => $validated['case_number'],
                'to_office' => $validated['to_office'],
                'suspect_name' => $firstSuspect['name'] ?? '',
                'suspect_gender' => $firstSuspect['gender'] ?? null,
                'suspect_age' => $firstSuspect['age'] ?? null,
                'suspect_address' => $validated['suspect_address'] ?? null,
            ]);

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
                            'active_substance' => $sampleData['active_substance'],
                            'package_quantity' => $sampleData['package_quantity'],
                            'unit' => $sampleData['unit'],
                        ]);
                        $submittedSampleIds[] = $sample->id;
                    }
                } else {
                    $newSample = Sample::create([
                        'test_request_id' => $testRequest->id,
                        'short_description' => $sampleData['short_description'],
                        'active_substance' => $sampleData['active_substance'],
                        'package_quantity' => $sampleData['package_quantity'],
                        'unit' => $sampleData['unit'],
                        'sample_form' => 'other',
                        'test_methods' => json_encode(['uv_vis']),
                        'condition' => 'baik',
                        'sample_status' => 'received',
                    ]);
                    $submittedSampleIds[] = $newSample->id;
                }
            }

            // Delete samples that were removed
            Sample::where('test_request_id', $testRequest->id)
                ->whereNotIn('id', $submittedSampleIds)
                ->delete();

            // DELETE old Berita Acara file (force re-generation)
            $baFilename = "Berita_Acara_Penerimaan_{$testRequest->request_number}_ID-{$testRequest->id}.html";
            $baFilepath = base_path("output/{$baFilename}");

            if (file_exists($baFilepath)) {
                @unlink($baFilepath);
                \Log::info('Deleted old BA file after edit', [
                    'request_id' => $testRequest->id,
                    'file' => $baFilename,
                ]);
            }

            DB::commit();

            return redirect()->route('requests.show', $id)
                ->with('success', 'Permintaan berhasil diupdate! Silakan generate ulang Berita Acara dengan data terbaru.');

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Error updating request', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function downloadDocument(TestRequest $testRequest, string $type)
    {

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

        // Validasi tipe dokumen yang diizinkan

        $allowedTypes = ['sample_receipt', 'handover_report', 'request_letter_receipt'];

        if (! in_array($type, $allowedTypes)) {

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

            // Simpan info untuk audit log

            $documentInfo = [

                'type' => $document->document_type,

                'filename' => $document->original_filename,

                'request_number' => $testRequest->request_number,

            ];

            // Hapus record dari database

            $document->forceDelete();

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

            // Ambil relasi lengkap
            $testRequest->loadMissing(['investigator', 'samples']);
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
                    'to_office' => $testRequest->to_office,
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
                    replaceExisting: true
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
                replaceExisting: true
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
