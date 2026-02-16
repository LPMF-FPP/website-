<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\SampleDisposalMethod;
use App\Enums\SampleDisposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SampleDisposalController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Dashboard with tabs: Eligible, History
     */
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'eligible');

        // Eligible samples for disposal
        $eligibleSamples = Sample::query()
            ->eligibleForDisposal()
            ->with(['testRequest.investigator', 'testProcesses'])
            ->latest()
            ->paginate(20, ['*'], 'eligible_page');

        // Disposal history
        $disposals = SampleDisposal::query()
            ->with(['samples', 'executedBy', 'witnessUser'])
            ->latest('executed_at')
            ->paginate(20, ['*'], 'history_page');

        return view('inventory.disposal.index', [
            'tab' => $tab,
            'eligibleSamples' => $eligibleSamples,
            'disposals' => $disposals,
            'methods' => SampleDisposalMethod::options(),
        ]);
    }

    /**
     * Show batch execution form
     */
    public function create(Request $request): View
    {
        $sampleIds = $request->get('sample_ids', []);

        if (is_string($sampleIds)) {
            $sampleIds = explode(',', $sampleIds);
        }

        $selectedSamples = Sample::query()
            ->whereIn('id', $sampleIds)
            ->eligibleForDisposal()
            ->with(['testRequest.investigator', 'testProcesses'])
            ->get();

        $witnessUsers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'title_prefix', 'title_suffix', 'role', 'rank', 'nrp', 'nip']);

        return view('inventory.disposal.create', [
            'selectedSamples' => $selectedSamples,
            'methods' => SampleDisposalMethod::options(),
            'witnessUsers' => $witnessUsers,
        ]);
    }

    /**
     * Execute batch disposal
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sample_ids' => 'required|array|min:1',
            'sample_ids.*' => 'exists:samples,id',
            'method' => 'required|in:bakar,kubur,hancur,lainnya',
            'witness_user_id' => 'nullable|exists:users,id',
            'witness_name' => 'required_without:witness_user_id|string|max:255',
            'witness_role' => 'required_without:witness_user_id|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $witnessUser = null;
        if (! empty($validated['witness_user_id'])) {
            $witnessUser = User::query()
                ->where('id', $validated['witness_user_id'])
                ->where('is_active', true)
                ->first();

            if (! $witnessUser) {
                return back()->withErrors([
                    'witness_user_id' => 'User saksi tidak valid atau tidak aktif.',
                ])->withInput();
            }
        }

        $witnessName = trim((string) ($validated['witness_name'] ?? ''));
        $witnessRole = trim((string) ($validated['witness_role'] ?? ''));

        if ($witnessUser && $witnessName === '') {
            $witnessName = trim((string) ($witnessUser->display_name_with_title ?: $witnessUser->name));
        }
        if ($witnessUser && $witnessRole === '') {
            $witnessRole = trim((string) ($witnessUser->rank ?? $witnessUser->role ?? ''));
        }

        $executor = Auth::user();
        $executedByName = trim((string) ($executor?->display_name_with_title ?? $executor?->name ?? ''));
        $executedByRole = trim((string) ($executor?->rank ?? $executor?->role ?? ''));

        // Verify all samples are eligible
        $samples = Sample::query()
            ->whereIn('id', $validated['sample_ids'])
            ->where('disposal_status', SampleDisposalStatus::ELIGIBLE)
            ->get();

        if ($samples->count() !== count($validated['sample_ids'])) {
            return back()->withErrors([
                'sample_ids' => 'Beberapa sampel tidak eligible untuk pemusnahan.',
            ])->withInput();
        }

        // Create disposal record
        $disposal = SampleDisposal::create([
            'batch_number' => SampleDisposal::generateBatchNumber(),
            'executed_at' => now(),
            'method' => SampleDisposalMethod::from($validated['method']),
            'witness_name' => $witnessName !== '' ? $witnessName : '-',
            'witness_role' => $witnessRole !== '' ? $witnessRole : '-',
            'witness_user_id' => $witnessUser?->id,
            'notes' => $validated['notes'] ?? null,
            'executed_by' => Auth::id(),
            'executed_by_name' => $executedByName !== '' ? $executedByName : '-',
            'executed_by_role' => $executedByRole !== '' ? $executedByRole : 'ANALIS',
            'created_by' => Auth::id(),
        ]);

        // Mark all samples as disposed
        foreach ($samples as $sample) {
            $sample->markAsDisposed($disposal);
        }

        return redirect()
            ->route('inventory.disposal.show', $disposal)
            ->with('success', "Pemusnahan {$samples->count()} sampel berhasil dieksekusi.");
    }

    /**
     * Show disposal details
     */
    public function show(SampleDisposal $disposal): View
    {
        $disposal->load([
            'samples.testRequest.investigator',
            'samples.testProcesses',
            'executedBy',
            'createdBy',
            'witnessUser',
        ]);

        return view('inventory.disposal.show', [
            'disposal' => $disposal,
        ]);
    }

    /**
     * Download Berita Acara Pemusnahan PDF
     */
    public function downloadPdf(SampleDisposal $disposal): Response
    {
        $disposal->load([
            'samples.testRequest.investigator',
            'samples.testProcesses',
            'executedBy',
            'witnessUser',
        ]);

        $pdf = Pdf::loadView('pdf.berita-acara-pemusnahan', [
            'disposal' => $disposal,
        ]);

        $filename = "berita-acara-pemusnahan-{$disposal->batch_number}.pdf";

        return $pdf->download($filename);
    }
}
