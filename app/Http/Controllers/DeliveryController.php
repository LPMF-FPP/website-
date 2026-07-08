<?php

namespace App\Http\Controllers;

use App\Models\CustomerSurvey;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\RemainingUnit;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Services\DocumentService;
use App\Support\QuantityFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    private function handoverBasePath(string $requestNumber): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]+/', '_', $requestNumber);

        return base_path("output/BA_Penyerahan_Ringkasan_{$sanitized}");
    }

    public function index(Request $request): Response|\Illuminate\Contracts\View\View
    {
        $requestSort = $request->query('request_sort', 'completed_at');
        $requestDirection = $request->query('request_direction', 'desc');

        if ($requestSort !== 'receipt_number') {
            $requestSort = 'completed_at';
            $requestDirection = 'desc';
        }
        if (! in_array($requestDirection, ['asc', 'desc'], true)) {
            $requestDirection = 'desc';
        }

        $requests = TestRequest::with([
            'investigator:id,name,jurisdiction,rank',
            'suspects:id,test_request_id,name,gender,age,order_no',
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
            ->select('id', 'request_number', 'receipt_number', 'investigator_id', 'suspect_name', 'status', 'submitted_at', 'completed_at', 'ready_for_delivery_at', 'created_at')
            ->when($requestSort === 'receipt_number', function ($query) use ($requestDirection) {
                $query->orderByRaw("COALESCE(receipt_number, request_number) {$requestDirection}");
            }, function ($query) {
                $query->orderByDesc('completed_at');
            })
            ->get();

        // Handle completed requests with search/filter/pagination
        $search = $request->query('search');
        $sort = $request->query('sort', 'completed_at');
        $direction = $request->query('direction', 'desc');

        // Validate sort column to prevent SQL injection
        if (! in_array($sort, ['request_number', 'receipt_number', 'completed_at', 'suspect_name'])) {
            $sort = 'completed_at';
        }
        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }

        $completedRequests = TestRequest::with([
            'investigator:id,name,jurisdiction,rank',
            'suspects:id,test_request_id,name,gender,age,order_no',
            'samples' => function ($query) {
                $query->select('id', 'test_request_id', 'short_description');
            },
        ])
            ->whereIn('status', ['completed', 'delivered'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('request_number', 'ilike', "%{$search}%")
                        ->orWhere('receipt_number', 'ilike', "%{$search}%")
                        ->orWhere('suspect_name', 'ilike', "%{$search}%")
                        ->orWhereHas('suspects', function ($suspects) use ($search) {
                            $suspects->where('name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('investigator', function ($inv) use ($search) {
                            $inv->where('name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(10, ['*'], 'completed_page')
            ->appends($request->except('completed_page'));

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

        if ($request->header('X-Fragment') === 'delivery-history') {
            return response()->view('delivery.partials.history-table', [
                'completedRequests' => $completedRequests,
            ]);
        }

        return view('delivery.index', compact('requests', 'deliveries', 'completedRequests'));

    }

    public function show(TestRequest $request)
    {

        $request->load([

            'investigator',

            'samples.analyst',

            'suspects',

            'samples.testProcesses.analyst',

            'evidenceUnits.remainingUnits', // Load data for labels

            'customerSurvey',

        ]);

        $formatQuantity = QuantityFormatter::formatQuantity(...);
        $appendUnit = QuantityFormatter::appendUnit(...);

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
        app(\App\Services\LabelService::class)->ensureAutoRemainingUnitsForRequest($request, Auth::id());

        // Reload evidenceUnits with remainingUnits after auto-generation
        $request->load('evidenceUnits.remainingUnits');

        $remainingUnitsBySampleId = $request->evidenceUnits
            ->filter(fn ($evidenceUnit) => $evidenceUnit->sample_id !== null)
            ->keyBy('sample_id');

        $request->samples->each(function ($sample) use ($formatQuantity, $appendUnit, $remainingUnitsBySampleId) {
            $evidenceUnit = $remainingUnitsBySampleId->get($sample->id);
            if (! $evidenceUnit instanceof EvidenceUnit) {
                return;
            }

            $remainingUnits = $evidenceUnit->remainingUnits->sortBy('id')->values();
            $remainingUnit = $remainingUnits->first();
            if (! $remainingUnit instanceof RemainingUnit) {
                return;
            }

            $sample->setAttribute('remaining_unit', $remainingUnit);
            $sample->setAttribute('remaining_units_count', $remainingUnits->count());

            if ($remainingUnits->count() <= 1) {
                return;
            }

            $leftoverQty = $remainingUnits->sum(fn (RemainingUnit $unit) => (float) $unit->qty_remaining);
            $leftoverUnit = $remainingUnit->uom ?: ($sample->unit ?? $sample->quantity_unit);

            $sample->setAttribute('leftover_quantity_value', $leftoverQty);
            $sample->setAttribute('leftover_quantity_display', $appendUnit($formatQuantity($leftoverQty), $leftoverUnit));
        });

        $samplesNeedingRemainingLabels = $request->samples
            ->filter(fn ($sample) => (float) ($sample->leftover_quantity_value ?? 0) > 0)
            ->values();

        $remainingLabelsRequired = $samplesNeedingRemainingLabels->isNotEmpty();

        // Check completion status for stepper
        $baExists = \App\Models\Document::where('test_request_id', $request->id)
            ->where('document_type', 'ba_penyerahan')
            ->exists();

        $labelsCount = $request->evidenceUnits->flatMap->remainingUnits->count();
        $labelsGenerated = $labelsCount > 0;
        $remainingLabelStepCompleted = ! $remainingLabelsRequired || $labelsGenerated;

        // Get last WhatsApp notification status
        $lastNotification = \App\Models\WhatsappOutbox::where('test_request_id', $request->id)
            ->where('milestone_key', 'READY_FOR_PICKUP')
            ->latest()
            ->first();

        $waNotificationSent = $lastNotification !== null;

        $survey = $request->customerSurvey;
        $surveyComplete = $survey && $survey->isComplete();

        $stepper = [
            1 => [
                'key' => 'ba_penyerahan',
                'title' => 'Berita Acara Penyerahan',
                'completed' => $baExists,
                'locked' => false,
            ],
            2 => [
                'key' => 'label_sisa',
                'title' => 'Label Sisa Sampel',
                'completed' => $remainingLabelStepCompleted,
                'locked' => ! $baExists,
                'count' => $labelsCount,
                'required' => $remainingLabelsRequired,
                'remaining_sample_count' => $samplesNeedingRemainingLabels->count(),
            ],
            3 => [
                'key' => 'notifikasi_wa',
                'title' => 'Notifikasi WhatsApp',
                'completed' => $waNotificationSent,
                'locked' => ! $remainingLabelStepCompleted,
            ],
            4 => [
                'key' => 'survei',
                'title' => 'Survei Kepuasan',
                'completed' => $surveyComplete,
                'locked' => ! $waNotificationSent,
            ],
            5 => [
                'key' => 'selesai',
                'title' => 'Selesaikan Penyerahan',
                'completed' => $request->status === 'completed',
                'locked' => ! $surveyComplete,
            ],
        ];

        return view('delivery.show', [

            'request' => $request,
            'delivery' => $delivery,
            'lastNotification' => $lastNotification,
            'stepper' => $stepper,
            'users' => \App\Models\User::orderBy('name')->get(['id', 'name']),

            'stages' => [
                'preparation' => 'Preparasi Sampel',
                'instrumentation' => 'Pengujian Instrumen',
                'interpretation' => 'Interpretasi Hasil',
            ],

        ]);

    }

    public function updateRemainingQuantities(Request $httpRequest, TestRequest $request)
    {
        abort_unless($httpRequest->user()?->hasAnyPermission(['penyerahan.edit', 'penyerahan.create']), 403);

        if ($request->status !== 'ready_for_delivery') {
            return back()->withErrors(['remaining_quantities' => 'Jumlah sisa sampel hanya dapat diedit saat permintaan masih siap diserahkan.']);
        }

        $request->loadMissing(['investigator', 'samples', 'evidenceUnits.remainingUnits']);

        $validated = $httpRequest->validate([
            'samples' => ['required', 'array'],
            'samples.*.qty_remaining' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ], [
            'samples.required' => 'Data sisa sampel wajib dikirim.',
            'samples.*.qty_remaining.numeric' => 'Jumlah sisa sampel harus berupa angka.',
            'samples.*.qty_remaining.min' => 'Jumlah sisa sampel tidak boleh kurang dari 0.',
            'samples.*.qty_remaining.max' => 'Jumlah sisa sampel terlalu besar.',
        ]);

        $sampleIds = $request->samples->pluck('id')->map(fn ($id) => (int) $id)->all();
        $submittedSamples = collect($validated['samples'] ?? []);
        $invalidSampleIds = $submittedSamples
            ->keys()
            ->map(fn ($sampleId) => (int) $sampleId)
            ->reject(fn ($sampleId) => in_array($sampleId, $sampleIds, true));

        if ($invalidSampleIds->isNotEmpty()) {
            return back()->withErrors(['remaining_quantities' => 'Data sampel tidak sesuai dengan permintaan penyerahan.'])->withInput();
        }

        if ($submittedSamples->isEmpty()) {
            return back()->withErrors(['remaining_quantities' => 'Pilih minimal satu sampel untuk diperbarui.'])->withInput();
        }

        foreach ($submittedSamples as $sampleId => $payload) {
            $sample = $request->samples->firstWhere('id', (int) $sampleId);
            $deliveredQty = is_numeric($sample?->package_quantity) ? (float) $sample->package_quantity : null;
            $qty = array_key_exists('qty_remaining', $payload) && $payload['qty_remaining'] !== null && $payload['qty_remaining'] !== ''
                ? (float) $payload['qty_remaining']
                : 0.0;

            if ($deliveredQty !== null && $qty > $deliveredQty) {
                return back()->withErrors([
                    'remaining_quantities' => 'Jumlah sisa sampel tidak boleh melebihi jumlah yang diserahkan.',
                ])->withInput();
            }

            $evidenceUnit = $request->evidenceUnits->firstWhere('sample_id', (int) $sample?->id);
            if ($evidenceUnit instanceof EvidenceUnit && $evidenceUnit->remainingUnits->count() > 1) {
                return back()->withErrors([
                    'remaining_quantities' => 'Sampel dengan beberapa label sisa harus diperbarui dari menu label agar setiap label tetap akurat.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($request, $submittedSamples, $httpRequest): void {
            $request->loadMissing(['samples', 'evidenceUnits.remainingUnits']);

            foreach ($submittedSamples as $sampleId => $payload) {
                $sample = $request->samples->firstWhere('id', (int) $sampleId);
                if (! $sample) {
                    continue;
                }

                $evidenceUnit = EvidenceUnit::query()->firstOrCreate(
                    ['sample_id' => $sample->id],
                    [
                        'request_id' => $request->id,
                        'receipt_code' => $request->receipt_number,
                        'sample_code' => $sample->sample_code,
                        'sample_type' => $sample->sample_category ?? $sample->sample_form,
                        'sample_desc' => $sample->short_description ?? $sample->sample_description,
                        'investigator_name' => $request->investigator?->name ?? $request->investigator?->rank_name,
                        'investigator_unit' => $request->investigator?->jurisdiction ?? $request->investigator?->satuan_kerja ?? $request->investigator?->unit,
                        'seal_status_received' => null,
                        'condition_received' => $sample->condition,
                        'received_at' => $sample->received_at ?? $request->received_at,
                        'received_by' => $sample->received_by ?? $httpRequest->user()?->id,
                    ]
                );

                $qty = array_key_exists('qty_remaining', $payload) && $payload['qty_remaining'] !== null && $payload['qty_remaining'] !== ''
                    ? (float) $payload['qty_remaining']
                    : 0.0;
                $uom = (string) ($sample->unit ?? $sample->quantity_unit ?? '');
                $deliveredQty = is_numeric($sample->package_quantity) ? (float) $sample->package_quantity : null;
                $testingQty = $deliveredQty !== null ? max($deliveredQty - $qty, 0.0) : null;

                $remainingUnit = RemainingUnit::query()
                    ->where('evidence_unit_id', $evidenceUnit->id)
                    ->orderBy('id')
                    ->first()
                    ?? new RemainingUnit(['evidence_unit_id' => $evidenceUnit->id]);

                $remainingUnit->fill([
                    'sample_code' => $sample->sample_code,
                    'qty_remaining' => $qty,
                    'uom' => $uom !== '' ? $uom : null,
                    'seal_status_delivered' => $remainingUnit->seal_status_delivered ?: 'disegel',
                    'delivered_at' => $remainingUnit->delivered_at ?? now(),
                    'delivered_by' => $remainingUnit->delivered_by ?? $httpRequest->user()?->id,
                ]);
                $remainingUnit->save();

                if ($testingQty !== null) {
                    $sample->forceFill([
                        'quantity' => $testingQty,
                        'quantity_unit' => $uom !== '' ? $uom : $sample->quantity_unit,
                    ])->save();
                }
            }
        });

        return redirect()
            ->route('delivery.show', $request)
            ->with('success', 'Jumlah sisa sampel berhasil diperbarui. Generate ulang Berita Acara Penyerahan jika dokumen sudah dibuat sebelumnya.');
    }

    public function editInvestigator(TestRequest $request)
    {
        abort_unless(request()->user()?->hasPermission('investigators.edit'), 403);

        $request->load('investigator');

        abort_unless($this->canEditDeliveryInvestigator($request), 403);

        return view('delivery.edit-investigator', [
            'request' => $request,
            'investigator' => $request->investigator,
        ]);
    }

    public function updateInvestigator(Request $httpRequest, TestRequest $request)
    {
        abort_unless($httpRequest->user()?->hasPermission('investigators.edit'), 403);

        $request->load('investigator');

        abort_unless($this->canEditDeliveryInvestigator($request), 403);

        $investigator = $request->investigator;
        abort_unless($investigator, 404);

        $rules = $investigator->is_polri
            ? [
                'investigator_name' => ['required', 'string', 'max:255'],
                'investigator_nrp' => ['required', 'string', 'max:50', Rule::unique('investigators', 'nrp')->ignore($investigator->id)],
                'investigator_rank' => ['required', 'string', 'max:255'],
                'investigator_jurisdiction' => ['required', 'string', 'max:255'],
                'investigator_phone' => ['required', 'string', 'max:20'],
                'investigator_email' => ['nullable', 'email', 'max:255', Rule::unique('investigators', 'email')->ignore($investigator->id)],
                'investigator_address' => ['nullable', 'string'],
            ]
            : [
                'external_name' => ['required', 'string', 'max:255'],
                'external_phone' => ['required', 'string', 'max:20'],
                'external_institution' => ['required', 'string', 'max:255'],
                'external_hp' => ['required', 'string', 'max:20'],
                'external_occupation' => ['required', 'string', 'max:255'],
            ];

        $validated = $httpRequest->validate($rules);

        $sharedError = null;

        $stateError = null;

        DB::transaction(function () use ($investigator, $request, $validated, &$sharedError, &$stateError): void {
            $lockedRequest = $request->newQuery()
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($lockedRequest->status !== 'ready_for_delivery' || (int) $lockedRequest->investigator_id !== (int) $investigator->id) {
                $stateError = 'Data penyidik hanya dapat diedit saat permintaan masih siap diserahkan.';

                return;
            }

            $lockedInvestigator = $investigator->newQuery()
                ->lockForUpdate()
                ->findOrFail($investigator->id);

            if ($lockedInvestigator->testRequests()->whereKeyNot($request->id)->exists()) {
                $sharedError = 'Data penyidik ini terhubung dengan permintaan lain. Ubah melalui Manajemen Penyidik agar dampaknya ditinjau lebih dulu.';

                return;
            }

            if ($lockedInvestigator->is_polri) {
                $lockedInvestigator->update([
                    'name' => $validated['investigator_name'],
                    'nrp' => $validated['investigator_nrp'],
                    'rank' => $validated['investigator_rank'],
                    'jurisdiction' => $validated['investigator_jurisdiction'],
                    'phone' => $validated['investigator_phone'],
                    'email' => $validated['investigator_email'] ?? null,
                    'address' => $validated['investigator_address'] ?? null,
                ]);
            } else {
                $lockedInvestigator->update([
                    'name' => $validated['external_name'],
                    'rank' => 'NON-POLRI',
                    'jurisdiction' => $validated['external_institution'],
                    'phone' => $validated['external_hp'],
                    'alt_phone' => $validated['external_phone'],
                    'institution' => $validated['external_institution'],
                    'occupation' => $validated['external_occupation'],
                ]);
            }
        });

        if ($sharedError !== null) {
            return back()
                ->withInput()
                ->withErrors(['investigator' => $sharedError]);
        }

        if ($stateError !== null) {
            return back()
                ->withInput()
                ->withErrors(['investigator' => $stateError]);
        }

        return redirect()
            ->route('delivery.show', $request)
            ->with('success', 'Data penyidik pada penyerahan berhasil diperbarui. Generate ulang Berita Acara Penyerahan jika dokumen sudah dibuat sebelumnya.');
    }

    private function canEditDeliveryInvestigator(TestRequest $request): bool
    {
        return $request->status === 'ready_for_delivery' && $request->investigator !== null;
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

        $surveyService = app(\App\Services\SurveyQuestionService::class);
        $activeQuestions = $surveyService->getQuestions();
        $questionKeys = collect($activeQuestions)->pluck('key')->toArray();

        // Build dynamic validation rules
        $answerRules = [];
        $answerMessages = [];
        foreach ($activeQuestions as $q) {
            $maxScale = count($q['scale'] ?? []) > 0 ? count($q['scale']) : 4;
            $answerRules["answers.{$q['key']}"] = ['required', 'integer', 'between:1,'.$maxScale];
            $answerMessages["answers.{$q['key']}.required"] = "Pertanyaan '{$q['label']}' wajib diisi.";
            $answerMessages["answers.{$q['key']}.between"] = "Skor '{$q['label']}' harus valid.";
        }

        $validatedData = $httpRequest->validate(array_merge([
            'respondent_name' => ['required', 'string', 'max:255'],
            'respondent_institution' => ['required', 'string', 'max:255'],
            'respondent_job_category' => ['required', Rule::in($jobCategories)],
            'request_type' => ['required', Rule::in($requestTypes)],
            'voluntary_statement' => ['accepted'],
            'answers' => ['required', 'array'],
            'suggestion' => ['required', 'string'],
            'complaint' => ['nullable', 'string'],
            'follow_up' => ['nullable', 'string'],
        ], $answerRules), array_merge([
            'respondent_name.required' => 'Nama responden wajib diisi.',
            'respondent_institution.required' => 'Instansi responden wajib diisi.',
            'respondent_job_category.required' => 'Kategori pekerjaan wajib dipilih.',
            'respondent_job_category.in' => 'Kategori pekerjaan tidak valid.',
            'request_type.required' => 'Jenis permintaan pengujian wajib dipilih.',
            'request_type.in' => 'Jenis permintaan pengujian tidak valid.',
            'voluntary_statement.accepted' => 'Pernyataan wajib disetujui.',
            'answers.required' => 'Semua pertanyaan survei wajib diisi.',
            'suggestion.required' => 'Saran/pesan/masukan wajib diisi.',
        ], $answerMessages));

        // Calculate score_avg based on active questions only (mapped to max 4 scale normalization if needed,
        // but currently we assume avg of raw scores is fine if consistent, or just raw avg)
        // User requested customization, let's keep it simple: average of values.

        $scoreAvg = collect($validatedData['answers'])
            ->filter(fn ($v, $k) => in_array($k, $questionKeys))
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

    /**
     * Kirim notifikasi WhatsApp "Siap Diambil" ke penyidik
     */
    public function sendPickupNotification(TestRequest $request, \App\Services\WhatsApp\NotificationService $notificationService)
    {
        if (! in_array($request->status, ['ready_for_delivery', 'completed'])) {
            return back()->with('error', 'Notifikasi hanya dapat dikirim untuk permintaan yang siap diserahkan.');
        }

        if (! $notificationService->isWhatsAppEnabled()) {
            return back()->with('error', 'Layanan WhatsApp tidak aktif.');
        }

        $request->load('investigator');

        if (! $request->investigator || ! $request->investigator->phone) {
            return back()->with('error', 'Nomor telepon penyidik tidak tersedia.');
        }

        $phone = $request->investigator->phone;
        $jid = $notificationService->formatJID($phone);

        $message = $notificationService->getMilestoneMessage('READY_FOR_PICKUP', [
            'resi' => $request->receipt_number,
            'nomor surat' => $request->request_number,
            'tersangka' => $request->suspect_name ?? '-',
            'pangkat' => $notificationService->getSalutation($request->investigator),
            'nama' => $request->investigator->name ?? '-',
            'greetings' => $notificationService->getTimeBasedGreeting(),
            'greeting' => $notificationService->getGreeting($request->investigator),
        ]);

        if (! $message) {
            return back()->with('error', 'Template pesan notifikasi tidak ditemukan.');
        }

        $outbox = \App\Models\WhatsappOutbox::updateOrCreate(
            [
                'test_request_id' => $request->id,
                'milestone_key' => 'READY_FOR_PICKUP',
            ],
            [
                'to_phone_e164' => \App\Support\PhoneNormalizer::toE164($phone),
                'to_jid' => $jid,
                'message_text' => $message,
                'status' => 'queued',
                'attempts' => 0,
                'last_error' => null,
            ]
        );

        \App\Jobs\SendWhatsAppNotificationJob::dispatch($outbox->id);

        return back()->with('success', 'Notifikasi "Siap Diambil" berhasil dikirim ke '.$request->investigator->name.'.');
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

            $formatQuantity = QuantityFormatter::formatQuantity(...);
            $appendUnit = QuantityFormatter::appendUnit(...);

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
        $currentSigner = Auth::user();
        if ($currentSigner !== null) {
            if ((int) $delivery->delivered_by !== (int) $currentSigner->id) {
                $delivery->forceFill(['delivered_by' => $currentSigner->id])->save();
            }
            $delivery->setRelation('deliveredBy', $currentSigner);
        }

        $delivery->loadMissing(['request.investigator', 'request.samples', 'request.evidenceUnits.remainingUnits', 'request.user', 'deliveredBy']);
        $req = $delivery->request;
        $inv = $req->investigator;

        // Check if document already exists to reuse number (prevent counter increment)
        $existingDoc = $docs->getExistingGenerated($req, 'ba_penyerahan');

        if ($existingDoc) {
            // Reuse number from existing filename to avoid incrementing counter
            // Format: {NUMBER}-ba-penyerahan.pdf
            $filename = $existingDoc->original_filename;
            $suffix = '-ba-penyerahan.pdf';

            if (str_ends_with($filename, $suffix)) {
                $baPenyerahanNumber = substr($filename, 0, -strlen($suffix));
            } else {
                // Fallback: strip extension and label
                $baseName = pathinfo($filename, PATHINFO_FILENAME);
                $baPenyerahanNumber = str_replace('-ba-penyerahan', '', $baseName);
            }
        } else {
            // Generate NEW document number
            $numberingService = app(\App\Services\NumberingService::class);
            $context = [
                'investigator_id' => $inv->id,
                'request_number' => $req->request_number,
            ];

            // Attempt to extract sequence from request_number to synchronize BA-ST number
            if (! empty($req->request_number)) {
                // Try Standard {SEQ}/... or .../{SEQ}/... format
                if (preg_match('/(?:^|[\/\-])(\d{1,5})(?:[\/\-]|$)/', $req->request_number, $m)) {
                    $context['forced_sequence'] = (int) $m[1];
                }
            }

            $baPenyerahanNumber = $numberingService->issue('ba_penyerahan', $context);
        }

        $baPenyerahanNumber = $this->canonicalizeBaPenyerahanNumber($baPenyerahanNumber);

        // Inject number into request metadata for the view to use
        // This ensures the view displays the reused number
        $meta = $req->metadata ?? [];
        if (! is_array($meta)) {
            $meta = [];
        }
        $meta['ba_penyerahan_number'] = $baPenyerahanNumber;
        $req->setAttribute('metadata', $meta);

        // Generate filesystem-safe baseName from document number
        $base = $docs->generateDocumentBaseName('ba_penyerahan', $baPenyerahanNumber);

        // render blade BA yang sudah kamu buat
        $html = view('pdf.ba-penyerahan', [
            'request' => $req,
            'delivery' => $delivery,
            'generatedAt' => now(),
        ])->render();

        // arsip HTML (replace existing to avoid duplication)
        $docs->storeGenerated($html, 'html', $inv, $req, 'ba_penyerahan_html', $base, replaceExisting: true, syncUser: request()->user());

        // HTML → PDF
        $pdf = Pdf::loadHTML($html)->setPaper('a4')
            ->setOption('isRemoteEnabled', true)->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 96)->output();

        // arsip PDF (replace existing to avoid duplication)
        $docs->storeGenerated($pdf, 'pdf', $inv, $req, 'ba_penyerahan', $base, replaceExisting: true, syncUser: request()->user());

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
        $delivery->loadMissing(['request.investigator', 'request.samples', 'request.evidenceUnits.remainingUnits', 'request.user', 'deliveredBy']);
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
            'delivery' => $delivery,
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

    private function canonicalizeBaPenyerahanNumber(string $number): string
    {
        $value = strtoupper(trim($number));

        if (str_contains($value, '/')) {
            $value = preg_replace('/\s*\/\s*/', '/', $value) ?? $value;
            $value = preg_replace('/\/{2,}/', '/', $value) ?? $value;

            return trim($value, '/');
        }

        if (preg_match('/^(BA-ST)-(\d+)-([IVXLCDM]+)-(\d{4})-([A-Z0-9]+)$/', $value, $m)) {
            return sprintf('%s/%s/%s/%s/%s', $m[1], str_pad($m[2], 3, '0', STR_PAD_LEFT), $m[3], $m[4], $m[5]);
        }

        return $value;
    }

    /**
     * Update Surat Pengantar data for a delivery.
     */
    public function updateSuratPengantar(Request $request, Delivery $delivery)
    {
        $validated = $request->validate([
            'surat_pengantar_number' => 'required|string|max:100',
            'surat_pengantar_date' => 'required|date',
        ], [
            'surat_pengantar_number.required' => 'Nomor surat pengantar harus diisi',
            'surat_pengantar_date.required' => 'Tanggal surat pengantar harus diisi',
        ]);

        $delivery->update([
            'has_surat_pengantar' => true,
            'surat_pengantar_number' => $validated['surat_pengantar_number'],
            'surat_pengantar_date' => $validated['surat_pengantar_date'],
        ]);

        return back()->with('success', 'Data Surat Pengantar berhasil disimpan.');
    }

    public function toggleLhuSignatures(Request $request, Delivery $delivery)
    {
        $delivery->update([
            'show_lhu_signatures' => $request->boolean('enabled', false),
        ]);

        return back()->with('success', $delivery->show_lhu_signatures
            ? 'Tanda tangan dan paraf akan muncul di LHU.'
            : 'Tanda tangan dan paraf disembunyikan dari LHU.');
    }

    public function updateVerifikator(Request $request, SampleTestProcess $process)
    {
        $validated = $request->validate([
            'verifikator_teknis_id' => ['nullable', 'integer', 'exists:users,id'],
            'verifikator_mutu_id' => ['nullable', 'integer', 'exists:users,id'],
            'verifikator_administrasi_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $metadata = $process->metadata ?? [];
        $metadata['verifikator_teknis_id'] = $validated['verifikator_teknis_id'] ?? null;
        $metadata['verifikator_mutu_id'] = $validated['verifikator_mutu_id'] ?? null;
        $metadata['verifikator_administrasi_id'] = $validated['verifikator_administrasi_id'] ?? null;
        $process->metadata = $metadata;
        $process->save();

        return back()->with('success', 'Verifikator berhasil disimpan.');
    }
}
