<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMutationMode;
use App\Models\InventoryOpeningBaseline;
use App\Services\InventoryMutationModeService;
use App\Services\InventoryOpeningBaselineApprovalService;
use App\Services\InventoryOpeningBaselineService;
use App\Services\InventoryReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryOpeningBaselineController extends Controller
{
    public function __construct(
        private readonly InventoryOpeningBaselineService $baselineService,
        private readonly InventoryOpeningBaselineApprovalService $approvalService,
        private readonly InventoryReadinessService $readinessService,
        private readonly InventoryMutationModeService $modeService,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View
    {
        return view('inventory.baseline.index', [
            'mode' => (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED),
            'baselines' => InventoryOpeningBaseline::query()
                ->where(function ($query) use ($request): void {
                    $query->where('generated_by', $request->user()->id);
                    if ($request->user()->hasPermission('inventori.stocktake.approve')) {
                        $query->orWhere('status', InventoryOpeningBaseline::STATUS_SUBMITTED);
                    }
                })
                ->with('generator:id,name')
                ->latest('generated_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'canGenerate' => $request->user()->hasPermission('inventori.view')
                && $request->user()->hasPermission('inventori.stocktake.submit'),
            'canManagePolicy' => $request->user()->hasPermission('inventori.location-access.manage'),
            'canStartBaseline' => $request->user()->hasPermission('inventori.location-access.manage'),
            'canDisable' => $request->user()->hasPermission('inventori.mutation.disable'),
            'readiness' => $this->readinessService->overview(),
        ]);
    }

    public function show(Request $request, InventoryOpeningBaseline $baseline): View
    {
        $data = $this->baselineService->show($request->user(), $baseline->id);
        $blockers = $this->presentBlockers($data['baseline']->blockers ?? []);
        $mode = (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED);

        return view('inventory.baseline.show', [
            ...$data,
            'blockers' => $blockers,
            'mode' => $mode,
            'canCapture' => $mode === InventoryMutationMode::OPENING_BASELINE
                && in_array($data['baseline']->status, [InventoryOpeningBaseline::STATUS_DRAFT, InventoryOpeningBaseline::STATUS_BLOCKED], true),
            'canSubmit' => $mode === InventoryMutationMode::OPENING_BASELINE
                && $data['baseline']->status === InventoryOpeningBaseline::STATUS_DRAFT
                && ($data['baseline']->blockers ?? []) === []
                && $data['visible_line_count'] === $data['baseline']->tuple_count
                && $data['visible_counted_count'] === $data['baseline']->tuple_count
                && $request->user()->hasPermission('inventori.stocktake.submit'),
            'canApprove' => $mode === InventoryMutationMode::OPENING_BASELINE
                && $data['baseline']->status === InventoryOpeningBaseline::STATUS_SUBMITTED
                && $request->user()->hasPermission('inventori.stocktake.approve'),
            'approvals' => $data['baseline']->approvals()->with(['submitter:id,name', 'approver:id,name'])->get(),
        ]);
    }

    public function submit(Request $request, InventoryOpeningBaseline $baseline): RedirectResponse
    {
        $this->approvalService->submit($request->user(), $baseline->id);

        return redirect()->route('inventory.opening-baseline.show', $baseline)->with('success', 'Hasil hitung dikirim untuk diperiksa. Jumlah kini terkunci dan tidak dapat diubah.');
    }

    public function decide(Request $request, InventoryOpeningBaseline $baseline): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,return'],
            'decision_note' => ['nullable', 'string', 'max:2000', 'required_if:decision,return'],
        ]);
        $this->approvalService->decide(
            $request->user(),
            $baseline->id,
            $validated['decision'] === 'approve',
            $validated['decision_note'] ?? null,
        );

        return redirect()->route('inventory.opening-baseline.show', $baseline)->with('success', $validated['decision'] === 'approve'
            ? 'Catatan stok awal disetujui. Semua selisih sudah dicatat dalam riwayat perubahan stok.'
            : 'Hitung ulang diminta. Jumlah fisik dapat diisi kembali.');
    }

    public function readinessEvidence(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(\App\Models\InventoryReadinessEvidence::CATEGORIES))],
            'signoff_role' => ['required', 'string', 'in:inventory_administrator,system_owner'],
            'evidence_reference' => ['required', 'string', 'max:255'],
        ]);
        $this->readinessService->recordEvidence(
            $request->user(),
            $validated['category'],
            $validated['evidence_reference'],
            $validated['signoff_role'],
        );

        return redirect()->route('inventory.opening-baseline.index')->with('success', 'Nomor dokumen dan persetujuan kesiapan tersimpan.');
    }

    public function readiness(Request $request): View
    {
        return view('inventory.baseline.readiness', [
            'mode' => (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED),
            'readiness' => $this->readinessService->overview(),
            'canAcceptAsInventoryAdmin' => $request->user()->hasPermission('inventori.readiness.inventory-admin.accept'),
            'canAcceptAsSystemOwner' => $request->user()->hasPermission('inventori.readiness.system-owner.accept'),
            'canActivate' => $request->user()->hasPermission('inventori.mutation.activate'),
            'canDisable' => $request->user()->hasPermission('inventori.mutation.disable'),
            'hasApprovedBaseline' => InventoryOpeningBaseline::query()->where('status', InventoryOpeningBaseline::STATUS_APPROVED)->exists(),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->modeService->activateRoutineMutations($request->user(), $validated['reason'] ?? null);

        return redirect()->route('inventory.opening-baseline.index')->with('success', 'Perubahan stok rutin diaktifkan setelah catatan stok awal dan persyaratan kesiapan disetujui.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->modeService->emergencyDisable($request->user(), $validated['reason']);

        return redirect()->route('inventory.opening-baseline.index')->with('success', 'Perubahan stok rutin dimatikan. Sistem menolak perubahan berikutnya.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $baseline = $this->baselineService->generate($request->user());

        return redirect()
            ->route('inventory.opening-baseline.show', $baseline)
            ->with('success', 'Daftar barang yang perlu dihitung berhasil dibuat.');
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $started = $this->modeService->beginOpeningBaseline($request->user(), $validated['reason'] ?? null);

        return redirect()
            ->route('inventory.opening-baseline.index')
            ->with('success', $started ? 'Pencatatan stok awal dimulai. Perubahan stok rutin tetap nonaktif.' : 'Pencatatan stok awal sudah dimulai.');
    }

    public function capture(Request $request, InventoryOpeningBaseline $baseline, int $line): RedirectResponse
    {
        $validated = $request->validate([
            'physical_qty' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
        ]);

        $this->baselineService->capture($request->user(), $baseline->id, $line, (float) $validated['physical_qty']);

        return back()->with('success', 'Jumlah fisik tersimpan. Jumlah stok dan riwayat perubahannya belum berubah.');
    }

    /** @param array<string, mixed> $blockers
     * @return list<string>
     */
    private function presentBlockers(array $blockers): array
    {
        $messages = [];
        if (($blockers['unclassified_item_types'] ?? []) !== []) {
            $types = collect($blockers['unclassified_item_types'])->pluck('label')->unique()->implode(', ');
            $messages[] = 'Penggunaan nomor lot belum ditentukan untuk jenis barang: '.$types.'. Atur kebijakan nomor lot terlebih dahulu.';
        }
        if (($blockers['restricted_location_view_grants_missing'] ?? 0) > 0) {
            $locationIds = implode(', ', $blockers['restricted_location_ids_missing_view_grant'] ?? []);
            $messages[] = 'Anda belum mendapat izin untuk melihat lokasi tertentu (ID: '.$locationIds.'). Nama lokasi dan jumlah stoknya tidak ditampilkan di sini.';
        }
        if (($blockers['duplicate_balance_tuples'] ?? 0) > 0) {
            $messages[] = 'Ada lebih dari satu catatan untuk barang, nomor lot, dan lokasi yang sama. Periksa catatan ini sebelum menyelesaikan pencatatan stok awal.';
        }
        if (($blockers['ledger_projection_mismatch_tuples'] ?? 0) > 0) {
            $messages[] = 'Jumlah stok beberapa barang berbeda dari riwayat perubahannya. Periksa perbedaan ini sebelum mengirim hasil hitung.';
        }
        if (($blockers['missing_master_identity_tuples'] ?? 0) > 0) {
            $messages[] = 'Beberapa catatan stok merujuk ke barang, nomor lot, atau lokasi yang tidak ditemukan. Lengkapi datanya sebelum melanjutkan.';
        }
        if (in_array('manifest_stale', $blockers, true)) {
            $messages[] = 'Data barang, izin akses, atau jumlah stok berubah setelah daftar dibuat. Buat daftar baru dan hitung ulang.';
        }
        if (in_array('source_changed_after_approval', $blockers, true)) {
            $messages[] = 'Data barang, izin akses, jumlah stok, atau riwayatnya berubah setelah persetujuan. Persetujuan lama tetap tersimpan. Buat catatan stok awal baru sebelum mengaktifkan perubahan stok.';
        }

        return $messages;
    }
}
