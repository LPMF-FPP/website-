<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryCycleCount;
use App\Models\InventoryCycleCountLine;
use App\Models\InventoryCycleCountRevision;
use App\Models\InventoryMutationMode;
use App\Services\InventoryCycleCountService;
use App\Services\InventoryLocationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InventoryCycleCountController extends Controller
{
    public function __construct(
        private readonly InventoryCycleCountService $cycleCountService,
        private readonly InventoryLocationAccessService $locationAccess,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View
    {
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($request->user());
        $counts = InventoryCycleCount::query()
            ->where(function ($query) use ($request): void {
                $query->where('created_by', $request->user()->id);
                if ($request->user()->hasPermission('inventori.stocktake.approve')) {
                    $query->orWhere('status', InventoryCycleCount::STATUS_PENDING_APPROVAL);
                }
            })
            ->with(['creator:id,name', 'submitter:id,name'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $counts->getCollection()->each(function (InventoryCycleCount $count) use ($visibleLocationIds): void {
            $visibleLines = $count->lines()->whereIn('location_id', $visibleLocationIds);
            $count->setAttribute('visible_tuple_count', $visibleLines->count());
            $count->setAttribute('visible_counted_count', $count->lines()->whereIn('location_id', $visibleLocationIds)
                ->whereNotNull('physical_qty')->count());
        });

        return view('inventory.cycle-counts.index', [
            'mode' => (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED),
            'counts' => $counts,
            'canCreate' => $request->user()->hasPermission('inventori.view') && $request->user()->hasPermission('inventori.stocktake.submit'),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $validated = $request->validate(['purpose' => ['nullable', 'string', 'max:255']]);
        $count = $this->cycleCountService->createDraft($request->user(), $validated['purpose'] ?? null);

        return redirect()->route('inventory.cycle-counts.show', $count)->with('success', 'Pemeriksaan stok dibuat. Pilih barang, nomor lot, dan lokasi yang akan dihitung.');
    }

    public function show(Request $request, InventoryCycleCount $cycleCount): View
    {
        $actor = $request->user();
        if ($cycleCount->created_by !== $actor->id && ! $actor->hasPermission('inventori.stocktake.approve')) {
            abort(403);
        }

        $visibleLocationIds = $this->locationAccess->visibleLocationIds($actor);
        $visibleTupleCount = $cycleCount->lines()->whereIn('location_id', $visibleLocationIds)->count();
        $visibleCountedCount = $cycleCount->lines()->whereIn('location_id', $visibleLocationIds)->whereNotNull('physical_qty')->count();
        $mode = (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED);
        $lines = $cycleCount->lines()
            ->whereIn('location_id', $visibleLocationIds)
            ->with('counter:id,name')
            ->orderBy('location_id')
            ->orderBy('item_id')
            ->orderByRaw('lot_id NULLS FIRST')
            ->paginate(50)
            ->withQueryString();

        $candidatePaginator = null;
        if ($cycleCount->created_by === $actor->id && $cycleCount->status === InventoryCycleCount::STATUS_DRAFT
            && $mode === InventoryMutationMode::ACTIVE) {
            $search = trim((string) $request->query('search', ''));
            $selectedKeys = $cycleCount->lines->map(fn (InventoryCycleCountLine $line): string => $this->tupleKey(
                (int) $line->item_id,
                $line->lot_id === null ? null : (int) $line->lot_id,
                (int) $line->location_id,
            ))->all();
            $candidateTuples = collect($this->cycleCountService->availableTuples($actor))
                ->reject(fn (array $tuple): bool => in_array($this->tupleKey($tuple['item_id'], $tuple['lot_id'], $tuple['location_id']), $selectedKeys, true))
                ->when($search !== '', fn (Collection $tuples): Collection => $tuples->filter(fn (array $tuple): bool => str_contains(mb_strtolower(implode(' ', [
                    $tuple['item_code_snapshot'] ?? '',
                    $tuple['item_name_snapshot'],
                    $tuple['lot_no_snapshot'] ?? '',
                    $tuple['location_name_snapshot'],
                ])), mb_strtolower($search))))
                ->values();
            $perPage = 25;
            $page = LengthAwarePaginator::resolveCurrentPage('candidates_page');
            $candidatePaginator = new LengthAwarePaginator(
                $candidateTuples->forPage($page, $perPage)->values(),
                $candidateTuples->count(),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'candidates_page'],
            );
            $candidatePaginator->withQueryString();
        }

        $canEdit = $cycleCount->created_by === $actor->id
            && $cycleCount->status === InventoryCycleCount::STATUS_DRAFT
            && $mode === InventoryMutationMode::ACTIVE;
        $revisionHistory = InventoryCycleCountRevision::query()->where('cycle_count_id', $cycleCount->id)
            ->select(['id', 'cycle_count_id', 'revision_number', 'revision_status', 'submitted_by', 'submitted_at'])
            ->with(['submitter:id,name', 'decision.actor:id,name'])
            ->orderByDesc('revision_number')
            ->get();

        return view('inventory.cycle-counts.show', [
            'cycleCount' => $cycleCount->load(['creator:id,name', 'submitter:id,name']),
            'lines' => $lines,
            'visibleTupleCount' => $visibleTupleCount,
            'visibleCountedCount' => $visibleCountedCount,
            'candidateTuples' => $candidatePaginator,
            'revisionHistory' => $revisionHistory,
            'search' => (string) $request->query('search', ''),
            'mode' => $mode,
            'canEdit' => $canEdit,
            'canSubmit' => $canEdit
                && $cycleCount->tuple_count > 0
                && $visibleTupleCount === $cycleCount->tuple_count
                && $cycleCount->counted_count === $cycleCount->tuple_count,
            'hasCompleteViewScope' => $visibleTupleCount === $cycleCount->tuple_count,
            'canApprove' => $mode === InventoryMutationMode::ACTIVE
                && $cycleCount->status === InventoryCycleCount::STATUS_PENDING_APPROVAL
                && $visibleTupleCount === $cycleCount->tuple_count
                && $actor->hasPermission('inventori.stocktake.approve'),
        ]);
    }

    public function addTuple(Request $request, InventoryCycleCount $cycleCount): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'min:1'],
            'lot_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);
        $this->cycleCountService->addTuple($request->user(), $cycleCount->id, (int) $validated['item_id'], isset($validated['lot_id']) ? (int) $validated['lot_id'] : null, (int) $validated['location_id']);

        return back()->with('success', 'Barang ditambahkan ke daftar pemeriksaan.');
    }

    public function removeTuple(Request $request, InventoryCycleCount $cycleCount, InventoryCycleCountLine $line): RedirectResponse
    {
        $this->cycleCountService->removeTuple($request->user(), $cycleCount->id, $line->id);

        return back()->with('success', 'Barang dihapus dari daftar pemeriksaan.');
    }

    public function capture(Request $request, InventoryCycleCount $cycleCount, InventoryCycleCountLine $line): RedirectResponse
    {
        $validated = $request->validate([
            'physical_qty' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'variance_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->cycleCountService->capture($request->user(), $cycleCount->id, $line->id, (float) $validated['physical_qty'], $validated['variance_reason'] ?? null);

        return back()->with('success', 'Jumlah fisik tersimpan. Jumlah stok dan riwayat perubahannya belum berubah.');
    }

    public function submit(Request $request, InventoryCycleCount $cycleCount): RedirectResponse
    {
        $count = $this->cycleCountService->submit($request->user(), $cycleCount->id);

        return redirect()->route('inventory.cycle-counts.show', $count)->with('success', $count->status === InventoryCycleCount::STATUS_COMPLETED
            ? 'Pemeriksaan selesai. Jumlah fisik cocok dengan catatan stok, jadi tidak ada perubahan.'
            : 'Hasil hitung dikirim untuk diperiksa. Jumlah stok belum berubah.');
    }

    public function decide(Request $request, InventoryCycleCount $cycleCount): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ]);
        $count = $this->cycleCountService->decide($request->user(), $cycleCount->id, $validated['decision'] === 'approve', $validated['reason'] ?? null);

        return redirect()->route('inventory.cycle-counts.show', $count)->with('success', $validated['decision'] === 'approve'
            ? 'Selisih disetujui dan jumlah stok diperbarui.'
            : 'Hasil hitung ditolak. Jumlah stok tidak berubah, dan pemeriksaan dapat diulang.');
    }

    private function tupleKey(int $itemId, ?int $lotId, int $locationId): string
    {
        return $itemId.'|'.($lotId === null ? 'null' : (string) $lotId).'|'.$locationId;
    }
}
