<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Services\SupplementalRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplementalSampleController extends Controller
{
    public function create(TestRequest $testRequest): View|RedirectResponse
    {
        abort_unless(request()->user()?->hasPermission('penyerahan.create'), 403);
        $testRequest->loadMissing('delivery');

        if (! in_array($testRequest->status, ['ready_for_delivery', 'completed'], true) || ! $this->isCollectionComplete($testRequest)) {
            return redirect()->route('requests.show', $testRequest)
                ->with('error', 'Suplemen tertaut hanya digunakan setelah permintaan awal selesai atau hasil telah diambil.');
        }

        $testRequest->loadMissing('investigator');

        return view('requests.supplemental-sample-create', [
            'testRequest' => $testRequest,
            'sampleForms' => ['powder', 'pill', 'liquid', 'plant', 'crystal', 'paste', 'capsule', 'other'],
            'sampleCategories' => [
                'narkotika' => 'Narkotika',
                'prekursor' => 'Prekursor',
                'zat_adiktif' => 'Zat Adiktif',
                'obat_keras' => 'Obat Keras',
                'other' => 'Lainnya',
            ],
            'otherSampleOptions' => Sample::OTHER_SAMPLE_CATEGORIES,
        ]);
    }

    public function store(Request $request, TestRequest $testRequest, SupplementalRequestService $supplementalRequestService): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('penyerahan.create'), 403);

        $validated = $request->validate([
            'supplement_reason' => ['required', 'string', 'max:2000'],
            'collection_confirmation' => ['accepted'],
            'short_description' => ['required', 'string', 'max:255'],
            'sample_form' => ['required', 'string', Rule::in(['powder', 'pill', 'liquid', 'plant', 'crystal', 'paste', 'capsule', 'other'])],
            'sample_category' => ['required', 'string', Rule::in(['narkotika', 'prekursor', 'zat_adiktif', 'obat_keras', 'other'])],
            'other_sample_category' => ['required_if:sample_category,other', 'nullable', 'string', Rule::in(array_keys(Sample::OTHER_SAMPLE_CATEGORIES))],
            'sample_description' => ['nullable', 'string'],
            'sample_weight' => ['nullable', 'numeric', 'min:0'],
            'package_quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:50'],
            'condition' => ['required', 'string', Rule::in(['baik', 'rusak', 'basah', 'kering'])],
        ]);

        $supplement = $supplementalRequestService->create(
            $testRequest,
            $request->user(),
            $validated['supplement_reason'],
            $request->boolean('collection_confirmation'),
            $validated
        );

        return redirect()
            ->route('review.create', ['request_id' => $supplement->id])
            ->with('success', 'Permintaan suplemen berhasil dibuat tertaut ke resi awal. Lanjutkan kaji ulang sampel suplemen.');
    }

    private function isCollectionComplete(TestRequest $testRequest, ?\App\Models\Delivery $delivery = null): bool
    {
        $delivery ??= $testRequest->delivery;

        return $testRequest->status === 'completed' || $delivery?->hasBeenCollected() === true;
    }
}
