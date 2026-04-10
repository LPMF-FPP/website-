<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Enums\SampleDisposalMethod;
use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
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
        $selectedSampleIds = collect(Session::get('inventory.disposal.selected_sample_ids', []))
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $eligibleSamplesQuery = Sample::query()
            ->eligibleForDisposal()
            ->with(['testRequest.investigator', 'testProcesses'])
            ->latest();

        // Eligible samples for disposal
        $eligibleSamples = (clone $eligibleSamplesQuery)
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
            'selectedSampleIds' => $selectedSampleIds,
            'eligibleSampleIds' => (clone $eligibleSamplesQuery)->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ]);
    }

    /**
     * Show batch execution form
     */
    public function create(Request $request): View|RedirectResponse
    {
        $sampleIds = $request->get('sample_ids', []);

        if ($request->boolean('all')) {
            $sampleIds = Sample::query()
                ->eligibleForDisposal()
                ->pluck('id')
                ->all();
        }

        if (is_string($sampleIds)) {
            $sampleIds = explode(',', $sampleIds);
        }

        $sampleIds = collect($sampleIds)
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $selectedSamples = Sample::query()
            ->whereIn('id', $sampleIds)
            ->eligibleForDisposal()
            ->with(['testRequest.investigator', 'testProcesses'])
            ->get();

        if (count($sampleIds) !== $selectedSamples->count()) {
            Session::forget('inventory.disposal.selected_sample_ids');

            return redirect()
                ->route('inventory.disposal.index', ['tab' => 'eligible'])
                ->with('error', 'Beberapa sampel tidak lagi eligible untuk pemusnahan. Silakan pilih ulang batch Anda.');
        }

        Session::put('inventory.disposal.selected_sample_ids', $selectedSamples->pluck('id')->all());

        $witnessUsers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'title_prefix', 'title_suffix', 'role', 'rank', 'nrp', 'nip']);

        $oldWitnesses = old('witnesses');
        $witnessRows = is_array($oldWitnesses) && $oldWitnesses !== []
            ? $oldWitnesses
            : [
                ['user_id' => '', 'name' => '', 'role' => ''],
            ];

        return view('inventory.disposal.create', [
            'selectedSamples' => $selectedSamples,
            'methods' => SampleDisposalMethod::options(),
            'witnessUsers' => $witnessUsers,
            'witnessRows' => $witnessRows,
        ]);
    }

    /**
     * Execute batch disposal
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sample_ids' => 'required|array|min:1',
            'sample_ids.*' => 'distinct|exists:samples,id',
            'method' => 'required|in:bakar,kubur,hancur,lainnya',
            'executor_name' => 'nullable|string|max:255',
            'executor_role' => 'nullable|string|max:255',
            'executor_identity' => 'nullable|string|max:255',
            'witnesses' => 'required|array|min:1',
            'witnesses.*.user_id' => 'nullable|exists:users,id',
            'witnesses.*.name' => 'nullable|string|max:255',
            'witnesses.*.role' => 'nullable|string|max:255',
            'witnesses.*.identity' => 'nullable|string|max:255',
            'approver_name' => 'nullable|string|max:255',
            'approver_role' => 'nullable|string|max:255',
            'approver_identity' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $resolvedWitnessEntries = $this->resolveWitnessEntries($validated['witnesses']);

        if ($resolvedWitnessEntries === []) {
            return back()->withErrors([
                'witnesses' => 'Isi minimal satu saksi yang valid.',
            ])->withInput();
        }

        $primaryWitness = $resolvedWitnessEntries[0];

        $executor = Auth::user();
        $executedByName = trim((string) ($validated['executor_name'] ?? ''));
        $executedByRole = trim((string) ($validated['executor_role'] ?? ''));
        $executedByIdentity = trim((string) ($validated['executor_identity'] ?? ''));

        if ($executedByName === '') {
            $executedByName = trim((string) ($executor?->display_name_with_title ?? $executor?->name ?? ''));
        }

        if ($executedByRole === '') {
            $executedByRole = trim((string) ($executor?->rank ?? $executor?->role ?? ''));
        }

        if ($executedByIdentity === '') {
            $executorNumber = $executor?->nrp ?: $executor?->nip;
            $executorNumberLabel = $executor?->nrp ? 'NRP:' : ($executor?->nip ? 'NIP:' : null);
            $executedByIdentity = trim((string) ($executorNumberLabel && $executorNumber ? $executorNumberLabel.' '.$executorNumber : ''));
        }

        $approverName = trim((string) ($validated['approver_name'] ?? ''));
        $approverRole = trim((string) ($validated['approver_role'] ?? ''));
        $approverIdentity = trim((string) ($validated['approver_identity'] ?? ''));

        $samples = collect();
        $disposal = DB::transaction(function () use ($validated, $resolvedWitnessEntries, $primaryWitness, $executedByName, $executedByRole, $executedByIdentity, $approverName, $approverRole, $approverIdentity, &$samples) {
            // Re-check eligibility in transaction so production data can be picked up from lifecycle rules.
            $samples = Sample::query()
                ->whereIn('id', $validated['sample_ids'])
                ->eligibleForDisposal()
                ->lockForUpdate()
                ->get();

            if ($samples->count() !== count($validated['sample_ids'])) {
                return null;
            }

            $disposal = $this->createDisposalRecord([
                'executed_at' => now(),
                'method' => SampleDisposalMethod::from($validated['method']),
                'witness_name' => $primaryWitness['name'],
                'witness_role' => $primaryWitness['role'],
                'witness_user_id' => $primaryWitness['user_id'],
                'witness_entries' => $resolvedWitnessEntries,
                'notes' => $validated['notes'] ?? null,
                'executed_by' => Auth::id(),
                'executed_by_name' => $executedByName !== '' ? $executedByName : '-',
                'executed_by_role' => $executedByRole !== '' ? $executedByRole : 'ANALIS',
                'executed_by_identity' => $executedByIdentity !== '' ? $executedByIdentity : null,
                'approver_name' => $approverName !== '' ? $approverName : '-',
                'approver_role' => $approverRole !== '' ? $approverRole : null,
                'approver_identity' => $approverIdentity !== '' ? $approverIdentity : null,
                'created_by' => Auth::id(),
            ]);

            foreach ($samples as $sample) {
                $sample->markAsDisposed($disposal);
            }

            return $disposal;
        });

        if (! $disposal) {
            return back()->withErrors([
                'sample_ids' => 'Beberapa sampel tidak eligible untuk pemusnahan.',
            ])->withInput();
        }

        Session::forget('inventory.disposal.selected_sample_ids');

        return redirect()
            ->route('inventory.disposal.show', $disposal)
            ->with('success', "Pemusnahan {$samples->count()} sampel berhasil dieksekusi.");
    }

    private function resolveWitnessEntries(array $witnesses): array
    {
        $resolved = [];

        foreach ($witnesses as $index => $witness) {
            $userId = (int) ($witness['user_id'] ?? 0);
            $name = trim((string) ($witness['name'] ?? ''));
            $role = trim((string) ($witness['role'] ?? ''));
            $identity = trim((string) ($witness['identity'] ?? ''));

            if ($userId === 0 && $name === '' && $role === '') {
                continue;
            }

            $witnessUser = null;
            if ($userId > 0) {
                $witnessUser = User::query()
                    ->where('id', $userId)
                    ->where('is_active', true)
                    ->first();

                if (! $witnessUser) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "witnesses.{$index}.user_id" => 'User saksi tidak valid atau tidak aktif.',
                    ]);
                }
            }

            if ($witnessUser && $name === '') {
                $name = trim((string) ($witnessUser->display_name_with_title ?: $witnessUser->name));
            }

            if ($witnessUser && $role === '') {
                $role = trim((string) ($witnessUser->rank ?? $witnessUser->role ?? ''));
            }

            if ($name === '' || $role === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "witnesses.{$index}.name" => 'Nama dan jabatan saksi wajib diisi.',
                    "witnesses.{$index}.role" => 'Nama dan jabatan saksi wajib diisi.',
                ]);
            }

            $resolved[] = [
                'user_id' => $witnessUser?->id,
                'name' => $name,
                'role' => $role,
                'identity' => $identity !== ''
                    ? $identity
                    : trim((string) ($witnessUser?->nrp ? 'NRP: '.$witnessUser->nrp : ($witnessUser?->nip ? 'NIP: '.$witnessUser->nip : ''))),
            ];
        }

        return $resolved;
    }

    /**
     * Retry disposal creation when concurrent requests hit the same batch number.
     */
    private function createDisposalRecord(array $attributes): SampleDisposal
    {
        $attempts = 0;

        while ($attempts < 5) {
            $attempts++;

            try {
                return SampleDisposal::create([
                    'batch_number' => SampleDisposal::generateBatchNumber(),
                    ...$attributes,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateBatchNumberException($exception) || $attempts >= 5) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Gagal membuat nomor batch pemusnahan yang unik.');
    }

    private function isDuplicateBatchNumberException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains($message, 'batch_number');
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
