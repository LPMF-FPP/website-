<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SampleStatus;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdditionalSampleController extends Controller
{
    public function create(TestRequest $testRequest): View|RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyPermission(['pengujian.create', 'pengujian.edit']), 403);

        if (! in_array($testRequest->status, ['in_testing', 'analysis', 'quality_check'], true)) {
            return redirect()->route('testing.show', $testRequest)
                ->with('error', 'Sampel hanya dapat ditambahkan dari pengujian aktif. Jika permintaan berada di tahap penyerahan, gunakan alur buka kembali atau suplemen.');
        }

        $testRequest->loadMissing('investigator');

        return view('requests.additional-sample', [
            'testRequest' => $testRequest,
            'sampleForms' => ['powder', 'pill', 'liquid', 'plant', 'crystal', 'paste', 'capsule', 'other'],
            'otherSampleOptions' => Sample::OTHER_SAMPLE_CATEGORIES,
            'sampleCategories' => [
                'narkotika' => 'Narkotika',
                'prekursor' => 'Prekursor',
                'zat_adiktif' => 'Zat Adiktif',
                'obat_keras' => 'Obat Keras',
                'other' => 'Lainnya',
            ],
        ]);
    }

    public function store(Request $request, TestRequest $testRequest): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyPermission(['pengujian.create', 'pengujian.edit']), 403);

        $validated = $request->validate([
            'supplement_reason' => ['required', 'string', 'max:2000'],
            'short_description' => ['required', 'string', 'max:255'],
            'sample_form' => ['required', 'string', Rule::in(['powder', 'pill', 'liquid', 'plant', 'crystal', 'paste', 'capsule', 'other'])],
            'sample_category' => ['required', 'string', Rule::in(['narkotika', 'prekursor', 'zat_adiktif', 'obat_keras', 'other'])],
            'other_sample_category' => ['required_if:sample_category,other', 'nullable', 'string', Rule::in(array_keys(Sample::OTHER_SAMPLE_CATEGORIES))],
            'sample_description' => ['nullable', 'string'],
            'sample_weight' => ['nullable', 'numeric', 'min:0'],
            'package_quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:50'],
            'condition' => ['required', 'string', Rule::in(['baik', 'rusak', 'basah', 'kering'])],
        ], [
            'short_description.required' => 'Deskripsi singkat sampel wajib diisi.',
            'supplement_reason.required' => 'Alasan penambahan sampel wajib diisi.',
            'sample_form.required' => 'Bentuk sampel wajib dipilih.',
            'sample_category.required' => 'Kategori sampel wajib dipilih.',
            'other_sample_category.required' => 'Kategori sampel lainnya wajib dipilih.',
            'package_quantity.min' => 'Jumlah sampel minimal satu.',
            'unit.required' => 'Satuan sampel wajib diisi.',
        ]);

        $sample = DB::transaction(function () use ($request, $testRequest, $validated): Sample {
            $lockedRequest = TestRequest::query()->lockForUpdate()->findOrFail($testRequest->id);

            if (! in_array($lockedRequest->status, ['in_testing', 'analysis', 'quality_check'], true)) {
                abort(409, 'Status permintaan berubah. Muat ulang halaman sebelum menambahkan sampel.');
            }

            $sample = Sample::query()->create([
                'test_request_id' => $lockedRequest->id,
                'short_description' => $validated['short_description'],
                'sample_form' => $validated['sample_form'],
                'sample_category' => $validated['sample_category'],
                'other_sample_category' => $validated['sample_category'] === 'other'
                    ? ($validated['other_sample_category'] ?? null)
                    : null,
                'sample_description' => $validated['sample_description'] ?? null,
                'sample_weight' => $validated['sample_weight'] ?? null,
                'package_quantity' => $validated['package_quantity'],
                'unit' => $validated['unit'],
                'condition' => $validated['condition'],
                'sample_status' => 'received',
                'status' => SampleStatus::FORM_SUBMITTED->value,
                'test_methods' => null,
                'requested_test_methods' => null,
                'active_substance' => null,
            ]);

            ActivityLogger::log(
                'ADDITIONAL_SAMPLE_ADDED',
                null,
                $sample,
                null,
                [
                    'sample_code' => $sample->sample_code,
                    'status' => $sample->status,
                ],
                [
                    'test_request_id' => $lockedRequest->id,
                    'receipt_number' => $lockedRequest->receipt_number,
                    'supplement_reason' => $validated['supplement_reason'],
                ],
                $request->user()?->id,
                $request
            );

            return $sample;
        });

        return redirect()
            ->route('testing.additional-samples.review', [$testRequest, $sample])
            ->with('success', 'Sampel tambahan berhasil dicatat. Lengkapi kaji ulang sampel ini sebelum memulai proses.');
    }
}
