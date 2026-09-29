<?php

namespace App\Http\Controllers;

use App\Enums\SampleStatus;
use App\Enums\TestMethod;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Support\ActivityLogger;
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
        $requests = TestRequest::with(['investigator:id,name', 'parentTestRequest:id,request_number,receipt_number'])
            ->whereIn('status', $allowedStatusesForForm)
            ->orderByDesc('created_at')
            ->get();

        $hasExplicitRequestId = $request->query->has('request_id');
        $selectedRequestId = $request->query('request_id');
        $selectedRequest = null;
        $ineligibleSelectedRequest = false;

        if ($selectedRequestId) {
            $selectedRequest = $this->loadRequestWithSamples($selectedRequestId);

            // Jika request terpilih sudah tidak berada di status yang diizinkan (sudah berpindah proses),
            // kosongkan agar otomatis memilih request pertama yang valid.
            if ($selectedRequest && ! in_array($selectedRequest->status, $allowedStatusesForForm, true)) {
                $selectedRequest = null;
                $selectedRequestId = null;
                $ineligibleSelectedRequest = true;
            }
        }

        if ($hasExplicitRequestId && ! $selectedRequest) {
            $ineligibleSelectedRequest = true;
        }

        if (! $selectedRequest && ! $hasExplicitRequestId && $requests->isNotEmpty()) {
            $selectedRequestId = $requests->first()->id;
            $selectedRequest = $this->loadRequestWithSamples($selectedRequestId);
        }

        $staffRoles = app(\App\Support\RoleCatalog::class)->staffRoles();

        $analysts = User::query()
            ->where('is_active', true)
            ->whereIn('role', $staffRoles)
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

        $existingActiveSubstances = Sample::query()
            ->whereNotNull('active_substance')
            ->where('active_substance', '!=', '')
            ->distinct()
            ->orderBy('active_substance')
            ->pluck('active_substance');

        return view('samples.test', [
            'requests' => $requests,
            'selectedRequest' => $selectedRequest,
            'selectedRequestId' => $selectedRequestId,
            'ineligibleSelectedRequest' => $ineligibleSelectedRequest,
            'reviewMode' => 'initial',
            'analysts' => $analysts,
            'methodOptions' => TestMethod::options(),
            'otherSampleOptions' => Sample::OTHER_SAMPLE_CATEGORIES,
            'existingPhysicalIdentifications' => $existingPhysicalIdentifications,
            'existingActiveSubstances' => $existingActiveSubstances,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'exists:test_requests,id'],
            'test_date' => ['required', 'date'],
            'samples' => ['required', 'array', 'min:1'],
            'samples.*.id' => ['required', 'exists:samples,id'],
            'samples.*.assigned_analyst_id' => ['required', $this->activeAnalystRule()],
            'samples.*.test_methods' => ['required', 'array', 'min:1'],
            'samples.*.test_methods.*' => ['string', Rule::in(array_map(fn ($method) => $method->value, TestMethod::cases()))],
            'samples.*.active_substance' => ['required', 'string', 'max:255'],
            'samples.*.physical_identification' => ['required', 'string'],
            'samples.*.quantity' => ['required', 'numeric', 'min:0.01'],
            // quantity_unit is now optional - server will use sample.unit from database
            'samples.*.quantity_unit' => ['nullable', 'string', 'max:50'],
            'samples.*.batch_number' => ['required', 'string', 'max:100'],
            'samples.*.expiry_date' => ['nullable', 'date'],
            'samples.*.test_type' => ['required', 'string', 'max:100'],
            'samples.*.notes' => ['nullable', 'string'],
            'samples.*.other_sample_category' => ['nullable', 'string', Rule::in(array_keys(Sample::OTHER_SAMPLE_CATEGORIES))],
        ], [
            'test_date.required' => 'Tanggal pengujian wajib diisi.',
            'test_date.date' => 'Tanggal pengujian tidak valid.',
            'samples.*.test_methods.required' => 'Metode pengujian wajib dipilih.',
            'samples.*.test_methods.*.in' => 'Metode pengujian tidak valid.',
            'samples.*.active_substance.required' => 'Zat aktif wajib diisi pada kaji ulang permintaan.',
            'samples.*.quantity.min' => 'Jumlah sampel harus lebih dari 0.',
            'samples.*.batch_number.required' => 'Nomor batch wajib diisi.',
            'samples.*.test_type.required' => 'Jenis atau fokus pengujian wajib dipilih.',
        ]);

        DB::transaction(function () use ($validated) {
            $testRequest = TestRequest::query()
                ->whereKey($validated['request_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($testRequest->status, ['submitted', 'verified', 'received'], true)) {
                throw ValidationException::withMessages([
                    'request_id' => 'Permintaan ini sudah berpindah tahap. Buka kembali halaman kaji ulang untuk melanjutkan dengan konteks yang benar.',
                ]);
            }

            foreach ($validated['samples'] as $index => $sampleData) {
                $sample = Sample::where('id', $sampleData['id'])
                    ->where('test_request_id', $validated['request_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($sample->status === SampleStatus::READY_FOR_DELIVERY->value
                    || $sample->testProcesses()->exists()
                    || $sample->disposal_id !== null) {
                    throw ValidationException::withMessages([
                        "samples.$index.id" => 'Sampel ini tidak lagi memenuhi syarat untuk kaji ulang awal.',
                    ]);
                }

                app(\App\Services\SampleReviewService::class)->review(
                    $sample,
                    $sampleData,
                    $validated['test_date'],
                    "samples.$index"
                );
            }

            TestRequest::where('id', $validated['request_id'])
                ->update(['status' => 'in_testing']);
        });

        return redirect()
            ->route('testing.show', $validated['request_id'])
            ->with('success', 'Kaji ulang permintaan berhasil disimpan. Lanjutkan ke pengujian.');
    }

    public function reviewAdditionalSample(TestRequest $testRequest, Sample $sample)
    {
        abort_unless($this->isEligibleAdditionalSample($testRequest, $sample), 404);

        $testRequest->loadMissing('parentTestRequest');
        $analysts = $this->activeAnalysts();

        return view('samples.test', [
            'requests' => collect([$testRequest->load('investigator:id,name')]),
            'selectedRequest' => $testRequest->setRelation('samples', collect([$sample->load('testProcesses')])),
            'selectedRequestId' => $testRequest->id,
            'analysts' => $analysts,
            'methodOptions' => TestMethod::options(),
            'otherSampleOptions' => Sample::OTHER_SAMPLE_CATEGORIES,
            'existingPhysicalIdentifications' => $this->existingPhysicalIdentifications(),
            'existingActiveSubstances' => $this->existingActiveSubstances(),
            'reviewMode' => 'additional',
            'ineligibleSelectedRequest' => false,
        ]);
    }

    public function storeAdditionalSample(Request $request, TestRequest $testRequest, Sample $sample)
    {
        $validated = $this->validateReviewPayload($request);

        if ((int) $validated['request_id'] !== (int) $testRequest->id
            || (int) ($validated['samples'][0]['id'] ?? 0) !== (int) $sample->id
            || count($validated['samples']) !== 1) {
            throw ValidationException::withMessages([
                'samples' => 'Kaji ulang sampel tambahan hanya dapat menyimpan satu sampel yang dipilih pada permintaan ini.',
            ]);
        }

        DB::transaction(function () use ($validated, $testRequest, $sample): void {
            $lockedRequest = TestRequest::query()->lockForUpdate()->findOrFail($testRequest->id);
            $lockedSample = Sample::query()
                ->whereKey($sample->id)
                ->where('test_request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isEligibleAdditionalSample($lockedRequest, $lockedSample)) {
                throw ValidationException::withMessages([
                    'samples.0.id' => 'Sampel ini sudah berubah status atau tidak lagi memenuhi syarat kaji ulang tambahan.',
                ]);
            }

            $before = [
                'status' => (string) $lockedSample->status,
                'sample_status' => (string) $lockedSample->sample_status,
            ];
            app(\App\Services\SampleReviewService::class)->review(
                $lockedSample,
                $validated['samples'][0],
                $validated['test_date'],
                'samples.0'
            );

            ActivityLogger::log(
                'ADDITIONAL_SAMPLE_REVIEWED',
                null,
                $lockedSample,
                $before,
                ['status' => SampleStatus::PREPARATION_PENDING->value, 'sample_status' => (string) $lockedSample->sample_status],
                ['test_request_id' => $lockedRequest->id, 'test_date' => $validated['test_date']]
            );
        });

        return redirect()
            ->route('testing.show', $testRequest)
            ->with('success', 'Kaji ulang sampel tambahan berhasil disimpan. Sampel masuk ke tahap preparasi.');
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

        return TestRequest::with(['parentTestRequest:id,request_number,receipt_number', 'samples' => function ($query) {
            $query->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)
                ->whereDoesntHave('testProcesses')
                ->whereNull('disposal_id')
                ->orderBy('id');
        }])->find($requestId);
    }

    private function isEligibleAdditionalSample(TestRequest $testRequest, Sample $sample): bool
    {
        return in_array($testRequest->status, ['in_testing', 'analysis', 'quality_check'], true)
            && (int) $sample->test_request_id === (int) $testRequest->id
            && $sample->status !== SampleStatus::READY_FOR_DELIVERY->value
            && $sample->disposal_id === null
            && ! $sample->testProcesses()->exists();
    }

    private function validateReviewPayload(Request $request): array
    {
        return $request->validate([
            'request_id' => ['required', 'exists:test_requests,id'],
            'test_date' => ['required', 'date'],
            'samples' => ['required', 'array', 'min:1'],
            'samples.*.id' => ['required', 'exists:samples,id'],
            'samples.*.assigned_analyst_id' => ['required', $this->activeAnalystRule()],
            'samples.*.test_methods' => ['required', 'array', 'min:1'],
            'samples.*.test_methods.*' => ['string', Rule::in(array_map(fn ($method) => $method->value, TestMethod::cases()))],
            'samples.*.active_substance' => ['required', 'string', 'max:255'],
            'samples.*.physical_identification' => ['required', 'string'],
            'samples.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'samples.*.quantity_unit' => ['nullable', 'string', 'max:50'],
            'samples.*.batch_number' => ['required', 'string', 'max:100'],
            'samples.*.expiry_date' => ['nullable', 'date'],
            'samples.*.test_type' => ['required', 'string', 'max:100'],
            'samples.*.notes' => ['nullable', 'string'],
            'samples.*.other_sample_category' => ['nullable', 'string', Rule::in(array_keys(Sample::OTHER_SAMPLE_CATEGORIES))],
        ], [
            'test_date.required' => 'Tanggal pengujian wajib diisi.',
            'test_date.date' => 'Tanggal pengujian tidak valid.',
            'samples.*.test_methods.required' => 'Metode pengujian wajib dipilih.',
            'samples.*.test_methods.*.in' => 'Metode pengujian tidak valid.',
            'samples.*.active_substance.required' => 'Zat aktif wajib diisi pada kaji ulang permintaan.',
            'samples.*.quantity.min' => 'Jumlah sampel harus lebih dari 0.',
            'samples.*.batch_number.required' => 'Nomor batch wajib diisi.',
            'samples.*.test_type.required' => 'Jenis atau fokus pengujian wajib dipilih.',
        ]);
    }

    private function activeAnalysts()
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', app(\App\Support\RoleCatalog::class)->staffRoles())
            ->orderBy('name')
            ->get();
    }

    private function activeAnalystRule(): \Illuminate\Validation\Rules\Exists
    {
        $staffRoles = app(\App\Support\RoleCatalog::class)->staffRoles();

        return Rule::exists('users', 'id')->where(fn ($query) => $query
            ->where('is_active', true)
            ->whereIn('role', $staffRoles));
    }

    private function existingPhysicalIdentifications()
    {
        return Sample::query()->whereNotNull('physical_identification')->where('physical_identification', '!=', '')
            ->distinct()->pluck('physical_identification')->sort()->values();
    }

    private function existingActiveSubstances()
    {
        return Sample::query()->whereNotNull('active_substance')->where('active_substance', '!=', '')
            ->distinct()->orderBy('active_substance')->pluck('active_substance');
    }
}
