<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\SampleDisposalMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\SampleDisposalRequest;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\User;
use App\Services\SampleDisposalEligibilityService;
use App\Services\SampleDisposalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SampleDisposalController extends Controller
{
    public function __construct(
        private readonly SampleDisposalEligibilityService $eligibility,
        private readonly SampleDisposalService $disposals,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View|Response
    {
        $this->disposals->authorizeRead($request->user());
        $filters = $request->validate(['tab' => ['nullable', 'in:eligible,history'], 'eligible_page' => ['nullable', 'integer', 'min:1'], 'history_page' => ['nullable', 'integer', 'min:1']]);
        try {
            $samples = $this->eligibility->query()->orderBy('id')->paginate(20, ['*'], 'eligible_page')->withQueryString();
            $monthly = $this->eligibility->monthlySummaries();

            return view('inventory.disposal.index', [
                'tab' => $filters['tab'] ?? 'eligible', 'eligibleSamples' => $samples,
                'projections' => $samples->getCollection()->mapWithKeys(fn ($sample) => [$sample->id => $this->eligibility->projection($sample)]),
                'monthlySummaries' => $monthly,
                'eligibleOverview' => ['total' => $samples->total(), 'monthly_count' => $monthly->count()],
                'disposals' => SampleDisposal::query()->withCount('samples')->latest('executed_at')->orderByDesc('id')->paginate(20, ['*'], 'history_page')->withQueryString(),
                'methods' => SampleDisposalMethod::options(), 'retentionDays' => Sample::disposalRetentionDays(),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return response()->view('inventory.disposal.unavailable', ['retryUrl' => route('inventory.disposal.index', $request->only('tab'))], 503);
        }
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->disposals->authorizeRead($request->user(), true);
        try {
            $ids = $request->input('sample_ids', []);
            if (is_string($ids)) {
                $ids = array_map('trim', explode(',', $ids));
            }
            $request->merge(['sample_ids' => $ids]);
            $data = $request->validate(['sample_ids' => ['array'], 'sample_ids.*' => ['integer', 'min:1', 'distinct'], 'month' => ['nullable', 'date_format:Y-m'], 'all' => ['nullable', 'boolean']]);
            if ($request->boolean('all')) {
                $ids = $this->eligibility->query()->orderBy('id')->pluck('id')->all();
            } elseif (! empty($data['month'])) {
                $ids = $this->eligibility->monthlySummaries()->firstWhere('key', $data['month'])['sample_ids'] ?? [];
            }
            if ($ids === []) {
                return redirect()->route('inventory.disposal.index', ['tab' => 'eligible'])->with('error', 'Tidak ada sampel yang memenuhi syarat pada pilihan ini. Muat ulang daftar dan pilih sampel lain.');
            }
            $preview = $this->disposals->preview($request->user(), $ids);
        } catch (ValidationException $exception) {
            Session::forget('inventory.disposal.selected_sample_ids');

            return redirect()->route('inventory.disposal.index', ['tab' => 'eligible'])->with('error', 'Pilihan sampel atau bulan tidak valid, atau sebagian sampel tidak lagi memenuhi syarat. Pilih lagi dari daftar terbaru.');
        }
        Session::put('inventory.disposal.selected_sample_ids', $preview['samples']->pluck('id')->all());
        $witnessRows = old('witnesses');
        if (! is_array($witnessRows) || ! array_is_list($witnessRows) || $witnessRows === [] || collect($witnessRows)->contains(fn ($row) => ! is_array($row))) {
            $witnessRows = [['user_id' => '', 'name' => '', 'role' => '', 'identity' => '']];
        }

        return view('inventory.disposal.create', [
            'selectedSamples' => $preview['samples'], 'manifest' => $preview['manifest'], 'reviewToken' => $preview['review_token'],
            'methods' => SampleDisposalMethod::options(),
            'witnessUsers' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rank', 'title_prefix', 'title_suffix']),
            'witnessRows' => $witnessRows,
            'selectedMonthLabel' => ! empty($data['month']) ? $this->eligibility->monthlySummaries()->firstWhere('key', $data['month'])['label'] ?? null : null,
        ]);
    }

    public function store(SampleDisposalRequest $request): RedirectResponse
    {
        try {
            $disposal = $this->disposals->execute($request->user(), $request->validated());
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors())
                ->withInput($request->except(['documentation_photos', 'review_token', '_token']));
        } catch (QueryException|RuntimeException $exception) {
            // Report the failure category without logging signer or sample payloads from SQL bindings.
            report(new RuntimeException('Pencatatan pemusnahan dibatalkan. Jenis kegagalan: '.get_class($exception)));

            return back()->withInput($request->except(['documentation_photos', 'review_token', '_token']))
                ->withErrors(['execution' => 'Pencatatan pemusnahan gagal dan transaksi dibatalkan. Tinjau ulang batch, periksa bukti, lalu coba kembali.']);
        }
        Session::forget('inventory.disposal.selected_sample_ids');

        return redirect()->route('inventory.disposal.show', $disposal)->with('success', 'Pemusnahan '.$disposal->sample_count.' sampel berhasil dieksekusi.');
    }

    public function show(SampleDisposal $disposal): View
    {
        $this->disposals->authorizeRead(request()->user());
        $disposal->load(['samples.testRequest.investigator', 'samples.testProcesses', 'executedBy', 'createdBy', 'witnessUser']);

        return view('inventory.disposal.show', ['disposal' => $disposal]);
    }

    public function downloadPdf(SampleDisposal $disposal): Response
    {
        $this->disposals->authorizeRead(request()->user());
        $disposal->load(['samples.testRequest.investigator', 'samples.testProcesses', 'executedBy', 'witnessUser']);

        return Pdf::loadView('pdf.berita-acara-pemusnahan', ['disposal' => $disposal])->download('berita-acara-pemusnahan-'.$disposal->batch_number.'.pdf');
    }

    public function documentationFile(SampleDisposal $disposal, string $photo): BinaryFileResponse
    {
        $this->disposals->authorizeRead(request()->user());
        $documentation = collect($disposal->documentation_photos_for_display)->firstWhere('path', rawurldecode($photo));
        abort_unless($documentation && $documentation['exists'], 404);

        return response()->file($documentation['absolute_path'], ['Content-Type' => $documentation['mime_type']]);
    }
}
