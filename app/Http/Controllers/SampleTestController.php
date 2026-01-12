<?php

namespace App\Http\Controllers;

use App\Enums\SampleStatus;
use App\Enums\TestMethod;
use App\Enums\TestProcessStage;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SampleTestController extends Controller
{
    public function create(Request $request)
    {
        // Tampilkan HANYA permintaan yang masih berada pada tahap sebelum pengujian
        // Artinya: sudah diajukan/diverifikasi/diterima, namun BELUM masuk proses pengujian.
        // Dengan ini, ketika data sudah berpindah ke proses berikutnya (in_testing/dst),
        // permintaan tersebut tidak akan muncul lagi di form pengujian.
        $allowedStatusesForForm = ['submitted', 'verified', 'received'];
        $requests = TestRequest::with(['investigator:id,name'])
            ->whereIn('status', $allowedStatusesForForm)
            ->orderByDesc('created_at')
            ->get();

        $selectedRequestId = $request->query('request_id');
        $selectedRequest = null;

        if ($selectedRequestId) {
            $selectedRequest = $this->loadRequestWithSamples($selectedRequestId);

            // Jika request terpilih sudah tidak berada di status yang diizinkan (sudah berpindah proses),
            // kosongkan agar otomatis memilih request pertama yang valid.
            if ($selectedRequest && ! in_array($selectedRequest->status, $allowedStatusesForForm, true)) {
                $selectedRequest = null;
            }
        }

        if (! $selectedRequest && $requests->isNotEmpty()) {
            $selectedRequestId = $requests->first()->id;
            $selectedRequest = $this->loadRequestWithSamples($selectedRequestId);
        }

        $analysts = User::query()
            ->whereIn('role', ['analis', 'penyelia', 'manajer_teknis'])
            ->orderBy('name')
            ->get();

        // Get existing physical identifications for autocomplete/dropdown
        $existingPhysicalIdentifications = Sample::query()
            ->whereNotNull('physical_identification')
            ->where('physical_identification', '!=', '')
            ->distinct()
            ->pluck('physical_identification')
            ->sort()
            ->values();

        return view('samples.test', [
            'requests' => $requests,
            'selectedRequest' => $selectedRequest,
            'selectedRequestId' => $selectedRequestId,
            'analysts' => $analysts,
            'methodOptions' => TestMethod::options(),
            'otherSampleOptions' => Sample::OTHER_SAMPLE_CATEGORIES,
            'existingPhysicalIdentifications' => $existingPhysicalIdentifications,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'exists:test_requests,id'],
            'test_date' => ['required', 'date'],
            'samples' => ['required', 'array', 'min:1'],
            'samples.*.id' => ['required', 'exists:samples,id'],
            'samples.*.assigned_analyst_id' => ['required', 'exists:users,id'],
            'samples.*.test_methods' => ['required', 'array', 'min:1'],
            'samples.*.test_methods.*' => ['string', Rule::in(array_map(fn ($method) => $method->value, TestMethod::cases()))],
            'samples.*.physical_identification' => ['required', 'string'],
            'samples.*.quantity' => ['required', 'numeric', 'min:0.01'],
            // quantity_unit is now optional - server will use sample.unit from database
            'samples.*.quantity_unit' => ['nullable', 'string', 'max:50'],
            'samples.*.batch_number' => ['nullable', 'string', 'max:100'],
            'samples.*.expiry_date' => ['nullable', 'date'],
            'samples.*.test_type' => ['nullable', 'string', 'max:100'],
            'samples.*.notes' => ['nullable', 'string'],
            'samples.*.other_sample_category' => ['nullable', 'string', Rule::in(array_keys(Sample::OTHER_SAMPLE_CATEGORIES))],
        ], [
            'samples.*.test_methods.required' => 'Metode pengujian wajib dipilih.',
            'samples.*.test_methods.*.in' => 'Metode pengujian tidak valid.',
            'samples.*.quantity.min' => 'Jumlah sampel harus lebih dari 0.',
        ]);

        $firstSampleId = $validated['samples'][0]['id'] ?? null;

        DB::transaction(function () use ($validated) {
            foreach ($validated['samples'] as $index => $sampleData) {
                $sample = Sample::where('id', $sampleData['id'])
                    ->where('test_request_id', $validated['request_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $requestedMethods = $this->normalizeMethods($sample->requested_test_methods ?? $sample->test_methods);
                $submittedMethods = array_values(array_unique($sampleData['test_methods']));

                $missingRequested = array_diff($requestedMethods, $submittedMethods);

                if (! empty($missingRequested)) {
                    throw ValidationException::withMessages([
                        "samples.$index.test_methods" => 'Metode pengujian pada permintaan tidak dapat dihapus.',
                    ]);
                }

                $otherCategory = $sampleData['other_sample_category'] ?? null;

                if ($sample->sample_type === 'other') {
                    if (! $otherCategory) {
                        throw ValidationException::withMessages([
                            "samples.$index.other_sample_category" => 'Pilih kategori sampel untuk jenis lainnya.',
                        ]);
                    }
                } else {
                    $otherCategory = null;
                }

                $sample->update([
                    'assigned_analyst_id' => $sampleData['assigned_analyst_id'],
                    'test_methods' => $submittedMethods,
                    'test_type' => $sampleData['test_type'] ?? null,
                    'physical_identification' => $sampleData['physical_identification'],
                    'quantity' => $sampleData['quantity'],
                    // Use unit from sample (set during request creation), not from user input
                    'quantity_unit' => $sample->unit ?? $sampleData['quantity_unit'] ?? null,
                    'batch_number' => $sampleData['batch_number'] ?? null,
                    'expiry_date' => $sampleData['expiry_date'] ?? null,
                    'test_date' => $validated['test_date'],
                    'notes' => $sampleData['notes'] ?? null,
                    'other_sample_category' => $otherCategory,
                    'status' => SampleStatus::PREPARATION_PENDING,
                ]);

                // Create workflow stages. Mark the first stage as started and mark
                // instrumentation as started when an analyst is assigned so that
                // the UI and workflow state reflect immediate movement.
                // Only create stages that are allowed in database: preparation, instrumentation, interpretation
                $stages = [
                    TestProcessStage::PREPARATION,
                    TestProcessStage::INSTRUMENTATION,
                    TestProcessStage::INTERPRETATION,
                ];
                $firstStage = $stages[0] ?? null;

                foreach ($stages as $stage) {
                    $createAttrs = [
                        'performed_by' => $stage === TestProcessStage::INSTRUMENTATION
                            ? $sampleData['assigned_analyst_id']
                            : null,
                    ];

                    // If this is the first stage, set started_at immediately.
                    if ($stage === $firstStage) {
                        $createAttrs['started_at'] = now();
                    }

                    // If instrumentation has an assigned analyst, mark it started.
                    if ($stage === TestProcessStage::INSTRUMENTATION && ! empty($createAttrs['performed_by'])) {
                        $createAttrs['started_at'] = $createAttrs['started_at'] ?? now();
                    }

                    SampleTestProcess::firstOrCreate(
                        [
                            'sample_id' => $sample->id,
                            'stage' => $stage->value,
                        ],
                        $createAttrs
                    );
                }
            }

            TestRequest::where('id', $validated['request_id'])
                ->update(['status' => 'in_testing']);
        });

        return redirect()
            ->route('testing.show', $validated['request_id'])
            ->with('success', 'Kaji ulang permintaan berhasil disimpan. Lanjutkan ke pengujian.');
    }

    public function reject(Request $request, TestRequest $testRequest)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $allowedStatusesForReview = ['submitted', 'verified', 'received'];

        if (! in_array($testRequest->status, $allowedStatusesForReview, true)) {
            return back()->withErrors([
                'rejection_reason' => 'Permintaan tidak dapat ditolak pada status ini.',
            ]);
        }

        $testRequest->update([
            'status' => 'rejected',
            'rejected_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('review.create')
            ->with('success', 'Permintaan berhasil ditolak.');
    }

    protected function loadRequestWithSamples(?int $requestId): ?TestRequest
    {
        if (! $requestId) {
            return null;
        }

        return TestRequest::with(['samples' => function ($query) {
            // Hanya muat sampel yang belum ready_for_delivery
            $query->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)
                ->orderBy('id');
        }])->find($requestId);
    }

    private function normalizeMethods($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }
}
