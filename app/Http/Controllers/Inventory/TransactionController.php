<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Services\InventoryLocationAccessService;
use App\Services\InventoryLotIdentityService;
use App\Services\InventoryMovementService;
use App\Services\InventoryMutationGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class TransactionController extends Controller
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected InventoryMutationGate $mutationGate,
        protected InventoryLocationAccessService $locationAccess,
        protected InventoryLotIdentityService $lotIdentity,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    // ==================== RECEIPT ====================

    public function receiptForm(): View
    {
        $lotTrackingModes = InventoryLotTrackingPolicy::query()->pluck('lot_tracking_mode', 'item_type')->all();

        return view('inventory.transactions.receipt', [
            'mode' => (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED),
            'items' => $this->locationAccess->itemsForTransaction(InventoryItem::active(), request()->user(), includeUnassociated: true)->orderBy('name')->get(),
            'selectedItemId' => request()->integer('item_id'),
            'locations' => $this->locationAccess->locationsForTransact(InventoryLocation::query(), request()->user())->orderBy('name')->get(),
            'referenceTypes' => InventoryMovement::REFERENCE_TYPES,
            'lotTrackingModes' => $lotTrackingModes,
        ]);
    }

    public function receiptSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'location_id' => ['nullable', 'exists:inventory_locations,id'],
            'qty' => ['required', 'numeric', 'min:0.001', 'max:99999999999.999'],
            'reference_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(InventoryMovement::REFERENCE_TYPES))],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'lot_mode' => ['required', 'string', 'in:existing,new,untracked'],
            'new_lot_no' => ['nullable', 'string', 'max:100'],
            'new_lot_expiry' => ['nullable', 'date', 'after_or_equal:today'],
            'location_mode' => ['nullable', 'string', 'in:existing,new'],
            'new_location_name' => ['nullable', 'string', 'max:255'],
            'new_location_type' => ['nullable', 'string', 'in:storage,lab,cold_room,quarantine'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);
        if (! $item->is_active) {
            return back()->withInput()->withErrors(['item_id' => 'Item sudah diarsipkan dan tidak dapat menerima stok baru.']);
        }

        $locationMode = $validated['location_mode'] ?? 'existing';
        if ($locationMode === 'new' || ! empty($validated['new_location_name']) || ! empty($validated['new_location_type'])) {
            return back()
                ->withInput()
                ->withErrors(['location_mode' => 'Tambahkan dan beri akses lokasi terlebih dahulu melalui Master Lokasi.']);
        }

        $locationId = (int) $validated['location_id'];
        if ($locationId < 1) {
            return back()->withInput()->withErrors(['location_id' => 'Lokasi penyimpanan wajib dipilih.']);
        }
        $lotId = $validated['lot_id'] ?? null;
        $newLotNumber = (string) ($validated['new_lot_no'] ?? '');
        $canonicalNewLotNumber = InventoryLotIdentityService::canonicalize($newLotNumber);
        $lotMode = $validated['lot_mode'];
        if ($lotMode === 'untracked' && ($lotId !== null || $newLotNumber !== '' || ! empty($validated['new_lot_expiry']))) {
            return back()
                ->withInput()
                ->withErrors(['lot_mode' => 'Mode tanpa lot tidak boleh berisi ID lot, nomor lot, atau tanggal kadaluarsa.']);
        }
        if ($newLotNumber !== '' && $canonicalNewLotNumber === '') {
            return back()
                ->withInput()
                ->withErrors(['new_lot_no' => 'Nomor lot harus berisi karakter selain spasi.']);
        }
        if ($lotMode === 'existing' && ($lotId === null || $canonicalNewLotNumber !== '' || ! empty($validated['new_lot_expiry']))) {
            return back()
                ->withInput()
                ->withErrors(['lot_id' => 'Pilih satu lot aktif yang sudah ada dan kosongkan bidang lot baru.']);
        } elseif ($lotMode === 'new' && $lotId !== null) {
            return back()
                ->withInput()
                ->withErrors(['lot_id' => 'Pilih lot yang sudah ada atau buat lot baru, bukan keduanya.']);
        } elseif ($lotMode === 'new' && $canonicalNewLotNumber === '') {
            return back()
                ->withInput()
                ->withErrors(['new_lot_no' => 'Masukkan nomor lot baru tanpa memilih lot yang sudah ada.']);
        }

        if ($lotMode === 'existing' && ! InventoryLot::query()
            ->whereKey($lotId)
            ->where('item_id', $item->id)
            ->where('status', 'ACTIVE')
            ->exists()) {
            return back()->withInput()->withErrors(['lot_id' => 'Lot harus milik item terpilih dan berstatus aktif.']);
        }

        if ($lotMode === 'new' && $item->requiresExpiry() && empty($validated['new_lot_expiry'])) {
            return back()
                ->withInput()
                ->withErrors(['new_lot_expiry' => 'Tanggal kadaluarsa wajib diisi untuk jenis item ini.']);
        }

        try {
            $actorId = $request->user()?->id;
            $requiredPermissions = ['inventori.receipt.create'];
            if ($lotMode === 'new') {
                $requiredPermissions[] = 'inventori.create';
            }

            $receiptResult = DB::transaction(function () use ($request, $actorId, $requiredPermissions, $validated, $locationId, $lotMode, $newLotNumber, &$lotId): array {
                $this->mutationGate->authorize($actorId, $requiredPermissions);
                $location = InventoryLocation::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();
                $this->locationAccess->authorizeLockedTransaction($request->user(), [$location->id]);

                $lockedItem = InventoryItem::query()->whereKey($validated['item_id'])->lockForUpdate()->firstOrFail();
                if (! $lockedItem->is_active) {
                    throw new RuntimeException('Item sudah diarsipkan dan tidak dapat menerima stok baru.');
                }
                if (! $this->locationAccess->itemHasTransactionAccess(
                    $request->user(),
                    $lockedItem->id,
                    $location->id,
                    allowUnassociated: true,
                )) {
                    throw new AuthorizationException('Item tidak tersedia dalam cakupan transaksi lokasi ini.');
                }

                $movement = $this->movementService->receipt([
                    'item_id' => $validated['item_id'],
                    'lot_id' => $lotId,
                    'lot_mode' => $lotMode,
                    'new_lot_no' => $newLotNumber,
                    'new_lot_expiry' => $validated['new_lot_expiry'] ?? null,
                    'location_id' => $locationId,
                    'qty' => $validated['qty'],
                    'uom' => $lockedItem->uom,
                    'reference_type' => $validated['reference_type'] ?? 'MANUAL',
                    'unit_cost' => $validated['unit_cost'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'performed_by' => $actorId,
                ], $request->user());
                $lotId = $movement->lot_id === null ? null : (int) $movement->lot_id;
                $balance = InventoryBalance::query()
                    ->where('item_id', $lockedItem->id)
                    ->where('location_id', $location->id)
                    ->when($lotId === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $lotId))
                    ->lockForUpdate()
                    ->firstOrFail();

                return [
                    'movement_id' => $movement->id,
                    'item_code' => $lockedItem->item_code,
                    'item_name' => $lockedItem->name,
                    'uom' => $lockedItem->uom,
                    'lot_mode' => $lotMode,
                    'lot_no' => $lotId === null ? null : InventoryLot::query()->whereKey($lotId)->value('lot_no'),
                    'lot_mode_label' => $lotMode === 'untracked'
                        ? 'Untracked, tanpa lot'
                        : (($lotMode === 'new' ? 'Lot baru' : 'Lot yang ada').': '.InventoryLot::query()->whereKey($lotId)->value('lot_no')),
                    'location_name' => $location->name,
                    'location_id' => $location->id,
                    'qty' => (string) $movement->qty,
                    'reference_type' => $movement->reference_type,
                    'resulting_balance' => (string) $balance->on_hand_qty,
                    'can_view_stock_card' => $request->user()->hasPermission('inventori.view')
                        && $this->locationAccess->canViewLocation($request->user(), $location),
                    'stock_card_url' => route('inventory.stock-card', array_filter([
                        'item_id' => $lockedItem->id,
                        'lot_id' => $lotId,
                        'location_id' => $location->id,
                    ], static fn (mixed $value): bool => $value !== null)),
                ];
            });

            return redirect()
                ->route('inventory.transaction.receipt')
                ->with('success', 'Penerimaan berhasil dicatat.')
                ->with('receipt_confirmation', $receiptResult);
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (QueryException $exception) {
            if ($this->lotIdentity->isIdentityConflict($exception)) {
                return back()
                    ->withInput()
                    ->withErrors(['error' => $this->lotIdentity->duplicateLotGuidance((int) $validated['item_id'], $newLotNumber)]);
            }

            throw $exception;
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== ISSUE ====================

    public function issueForm(): View
    {
        $lotTrackingModes = InventoryLotTrackingPolicy::query()->pluck('lot_tracking_mode', 'item_type')->all();

        return view('inventory.transactions.issue', [
            'items' => $this->locationAccess->itemsForTransaction(InventoryItem::active(), request()->user())->orderBy('name')->get(),
            'locations' => $this->locationAccess->locationsForTransact(InventoryLocation::query(), request()->user())->orderBy('name')->get(),
            'referenceTypes' => InventoryMovement::REFERENCE_TYPES,
            'lotTrackingModes' => $lotTrackingModes,
            'mode' => (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED),
        ]);
    }

    public function issueSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'lot_mode' => ['required', 'in:existing,untracked'],
            'location_id' => ['required', 'exists:inventory_locations,id'],
            'qty' => ['required', 'numeric', 'min:0.001', 'max:99999999999.999'],
            'expected_on_hand_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'expected_available_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'reference_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(InventoryMovement::REFERENCE_TYPES))],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);
        if (! $item->is_active) {
            return back()->withInput()->withErrors(['item_id' => 'Item sudah diarsipkan dan tidak dapat digunakan untuk transaksi baru.']);
        }
        $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
        if (! $policy
            || ! in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
            || trim((string) $policy->evidence_reference) === ''
            || $policy->decided_by_user_id === null
            || $policy->decided_at === null) {
            return back()->withInput()->withErrors(['error' => 'Kebijakan pelacakan lot belum disetujui untuk tipe item ini.']);
        }
        if (($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED && ($validated['lot_mode'] !== 'existing' || empty($validated['lot_id'])))
            || ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::UNTRACKED && ($validated['lot_mode'] !== 'untracked' || ! empty($validated['lot_id'])))) {
            return back()->withInput()->withErrors(['lot_mode' => 'Pilih satu lot aktif untuk item lot-tracked atau mode untracked tanpa lot.']);
        }
        if ($validated['lot_mode'] === 'existing' && ! InventoryLot::query()
            ->whereKey($validated['lot_id'])
            ->where('item_id', $item->id)
            ->where('status', 'ACTIVE')
            ->exists()) {
            return back()->withInput()->withErrors(['lot_id' => 'Lot harus milik item terpilih dan dapat dikeluarkan.']);
        }

        try {
            $result = DB::transaction(function () use ($request, $validated, $item): array {
                $movement = $this->movementService->issue([
                    'item_id' => $validated['item_id'],
                    'lot_id' => $validated['lot_id'] ?? null,
                    'lot_mode' => $validated['lot_mode'],
                    'location_id' => $validated['location_id'],
                    'qty' => $validated['qty'],
                    'expected_on_hand_qty' => $validated['expected_on_hand_qty'] ?? null,
                    'expected_available_qty' => $validated['expected_available_qty'] ?? null,
                    'uom' => $item->uom,
                    'reference_type' => $validated['reference_type'] ?? 'MANUAL',
                    'notes' => $validated['notes'] ?? null,
                ], $request->user());
                $balance = InventoryBalance::query()
                    ->where('item_id', $movement->item_id)
                    ->where('location_id', $movement->from_location_id)
                    ->when($movement->lot_id === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $movement->lot_id))
                    ->lockForUpdate()
                    ->firstOrFail();
                $location = InventoryLocation::query()->whereKey($movement->from_location_id)->firstOrFail();

                return [
                    'movement_id' => $movement->id,
                    'item_code' => $movement->item->item_code,
                    'item_name' => $movement->item->name,
                    'lot_mode' => $validated['lot_mode'],
                    'lot_no' => $movement->lot?->lot_no,
                    'location_name' => $location->name,
                    'location_id' => $location->id,
                    'qty' => (string) $movement->qty,
                    'uom' => $movement->uom,
                    'reference_type' => $movement->reference_type,
                    'resulting_balance' => (string) $balance->on_hand_qty,
                    'resulting_available' => (string) $balance->available_qty,
                    'can_view_stock_card' => $request->user()->hasPermission('inventori.view')
                        && $this->locationAccess->canViewLocation($request->user(), $location),
                    'stock_card_url' => route('inventory.stock-card', array_filter([
                        'item_id' => $movement->item_id,
                        'lot_id' => $movement->lot_id,
                        'location_id' => $location->id,
                    ], static fn (mixed $value): bool => $value !== null)),
                ];
            });

            return redirect()
                ->route('inventory.transaction.issue')
                ->with('success', 'Pengeluaran berhasil dicatat.')
                ->with('issue_confirmation', $result);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== TRANSFER ====================

    public function transferForm(): View
    {
        return view('inventory.transactions.transfer', [
            'items' => $this->locationAccess->itemsForTransaction(InventoryItem::active(), request()->user())->orderBy('name')->get(),
            'locations' => $this->locationAccess->locationsForTransact(InventoryLocation::query(), request()->user())->orderBy('name')->get(),
            'lotTrackingModes' => InventoryLotTrackingPolicy::query()->pluck('lot_tracking_mode', 'item_type')->all(),
            'referenceTypes' => InventoryMovement::REFERENCE_TYPES,
            'mode' => (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED),
        ]);
    }

    public function transferSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'lot_mode' => ['required', 'in:existing,untracked'],
            'from_location_id' => ['required', 'exists:inventory_locations,id'],
            'to_location_id' => ['required', 'exists:inventory_locations,id', 'different:from_location_id'],
            'qty' => ['required', 'numeric', 'min:0.001', 'max:99999999999.999'],
            'expected_on_hand_qty' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'expected_available_qty' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'reference_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(InventoryMovement::REFERENCE_TYPES))],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);
        if (! $item->is_active) {
            return back()->withInput()->withErrors(['item_id' => 'Item sudah diarsipkan dan tidak dapat digunakan untuk transaksi baru.']);
        }

        try {
            $result = DB::transaction(function () use ($request, $validated): array {
                $movement = $this->movementService->transfer([
                    'item_id' => $validated['item_id'],
                    'lot_id' => $validated['lot_id'] ?? null,
                    'lot_mode' => $validated['lot_mode'],
                    'from_location_id' => $validated['from_location_id'],
                    'to_location_id' => $validated['to_location_id'],
                    'qty' => $validated['qty'],
                    'expected_on_hand_qty' => $validated['expected_on_hand_qty'] ?? null,
                    'expected_available_qty' => $validated['expected_available_qty'] ?? null,
                    'reference_type' => $validated['reference_type'] ?? 'MANUAL',
                    'notes' => $validated['notes'] ?? null,
                ], $request->user());

                $balances = InventoryBalance::query()
                    ->where('item_id', $movement->item_id)
                    ->when($movement->lot_id === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $movement->lot_id))
                    ->whereIn('location_id', [$movement->from_location_id, $movement->to_location_id])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('location_id');
                $source = InventoryLocation::query()->whereKey($movement->from_location_id)->firstOrFail();
                $destination = InventoryLocation::query()->whereKey($movement->to_location_id)->firstOrFail();
                $canViewStockCard = $request->user()->hasPermission('inventori.view')
                    && $this->locationAccess->canViewLocation($request->user(), $source)
                    && $this->locationAccess->canViewLocation($request->user(), $destination);

                return [
                    'movement_id' => $movement->id,
                    'item_code' => $movement->item->item_code,
                    'item_name' => $movement->item->name,
                    'lot_mode' => $validated['lot_mode'],
                    'lot_no' => $movement->lot?->lot_no,
                    'source_location' => $source->name,
                    'source_location_id' => $source->id,
                    'destination_location' => $destination->name,
                    'destination_location_id' => $destination->id,
                    'qty' => (string) $movement->qty,
                    'uom' => $movement->uom,
                    'reference_type' => $movement->reference_type,
                    'source_balance' => (string) $balances->get($source->id)?->on_hand_qty,
                    'source_available' => (string) $balances->get($source->id)?->available_qty,
                    'destination_balance' => (string) $balances->get($destination->id)?->on_hand_qty,
                    'destination_available' => (string) $balances->get($destination->id)?->available_qty,
                    'can_view_stock_card' => $canViewStockCard,
                    'stock_card_url' => route('inventory.stock-card', array_filter([
                        'item_id' => $movement->item_id,
                        'lot_id' => $movement->lot_id,
                        'location_id' => $source->id,
                    ], static fn (mixed $value): bool => $value !== null)),
                ];
            });

            return redirect()
                ->route('inventory.transaction.transfer')
                ->with('success', 'Transfer berhasil dicatat.')
                ->with('transfer_confirmation', $result);
        } catch (QueryException) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Transfer tidak dapat disimpan karena saldo berubah. Tidak ada perubahan parsial; muat ulang konteks lalu coba kembali.']);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== STOCKTAKE ====================

    public function stocktakeForm(): RedirectResponse
    {
        return redirect()->route('inventory.opening-baseline.index');
    }

    public function stocktakeSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.item_id' => ['required', 'exists:inventory_items,id'],
            'rows.*.lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'rows.*.location_id' => ['required', 'exists:inventory_locations,id'],
            'rows.*.counted_qty' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $results = $this->movementService->processStocktake($validated['rows'], auth()->id());

            $adjustedCount = collect($results)->where('adjusted', true)->count();

            return redirect()
                ->route('inventory.transaction.stocktake')
                ->with('success', "Stock opname selesai. {$adjustedCount} penyesuaian dibuat.");
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== DISPOSE ====================

    public function disposeForm(): View
    {
        return view('inventory.transactions.dispose', [
            'items' => $this->locationAccess->itemsForTransaction(InventoryItem::active(), request()->user())->orderBy('name')->get(),
            'locations' => $this->locationAccess->locationsForTransact(InventoryLocation::query(), request()->user())->orderBy('name')->get(),
            'reasonCodes' => InventoryMovement::REASON_CODES,
            'lotTrackingModes' => InventoryLotTrackingPolicy::query()->pluck('lot_tracking_mode', 'item_type')->all(),
            'mode' => (string) (InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') ?? InventoryMutationMode::DISABLED),
        ]);
    }

    public function disposeSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'lot_mode' => ['required', 'in:existing,untracked'],
            'location_id' => ['required', 'exists:inventory_locations,id'],
            'qty' => ['required', 'numeric', 'min:0.001', 'max:99999999999.999'],
            'expected_on_hand_qty' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'expected_available_qty' => ['required', 'numeric', 'min:0', 'max:99999999999.999'],
            'reason_code' => ['required', 'string', 'in:'.implode(',', array_keys(InventoryMovement::REASON_CODES))],
            'review_confirmed' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);
        if (! $item->is_active) {
            return back()->withInput()->withErrors(['item_id' => 'Item sudah diarsipkan dan tidak dapat digunakan untuk transaksi baru.']);
        }

        try {
            $result = DB::transaction(function () use ($request, $validated): array {
                $movement = $this->movementService->dispose([
                    'item_id' => $validated['item_id'],
                    'lot_id' => $validated['lot_id'] ?? null,
                    'lot_mode' => $validated['lot_mode'],
                    'location_id' => $validated['location_id'],
                    'qty' => $validated['qty'],
                    'expected_on_hand_qty' => $validated['expected_on_hand_qty'],
                    'expected_available_qty' => $validated['expected_available_qty'],
                    'reason_code' => $validated['reason_code'],
                    'review_confirmed' => $validated['review_confirmed'],
                    'notes' => $validated['notes'] ?? null,
                ], $request->user());
                $balance = InventoryBalance::query()
                    ->where('item_id', $movement->item_id)
                    ->when($movement->lot_id === null, fn ($query) => $query->whereNull('lot_id'), fn ($query) => $query->where('lot_id', $movement->lot_id))
                    ->where('location_id', $movement->from_location_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $location = InventoryLocation::query()->whereKey($movement->from_location_id)->firstOrFail();
                $canViewStockCard = $request->user()->hasPermission('inventori.view')
                    && $this->locationAccess->canViewLocation($request->user(), $location);

                return [
                    'movement_id' => $movement->id,
                    'item_code' => $movement->item->item_code,
                    'item_name' => $movement->item->name,
                    'lot_mode' => $validated['lot_mode'],
                    'lot_no' => $movement->lot?->lot_no,
                    'location_name' => $location->name,
                    'location_id' => $location->id,
                    'qty' => (string) $movement->qty,
                    'uom' => $movement->uom,
                    'reason_code' => $movement->reason_code,
                    'reason_label' => InventoryMovement::REASON_CODES[$movement->reason_code],
                    'resulting_balance' => (string) $balance->on_hand_qty,
                    'resulting_available' => (string) $balance->available_qty,
                    'lot_status' => $movement->lot?->fresh()?->status,
                    'can_view_stock_card' => $canViewStockCard,
                    'stock_card_url' => route('inventory.stock-card', array_filter([
                        'item_id' => $movement->item_id,
                        'lot_id' => $movement->lot_id,
                        'location_id' => $location->id,
                    ], static fn (mixed $value): bool => $value !== null)),
                ];
            });

            return redirect()
                ->route('inventory.transaction.dispose')
                ->with('success', 'Disposal material inventori berhasil dicatat.')
                ->with('disposal_confirmation', $result);
        } catch (QueryException) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Disposal tidak dapat disimpan karena saldo berubah. Tidak ada perubahan parsial; muat ulang konteks dan tinjau kembali sebelum mencoba.']);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== AJAX HELPERS ====================

    public function getLotsForItem(Request $request)
    {
        $request->validate([
            'item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'lot_mode' => ['nullable', 'in:existing,untracked'],
        ]);

        $itemId = $request->input('item_id');
        $locationId = $request->input('location_id');
        $location = $locationId ? InventoryLocation::query()->find($locationId) : null;
        $context = $request->query('context');
        $historyContext = $context === 'history' || $context === null;
        $item = InventoryItem::query()->whereKey($itemId)->firstOrFail();
        if ($historyContext || $context === null) {
            abort_unless($request->user()->hasPermission('inventori.view'), 403);
        } else {
            abort_unless($locationId, 403);
            $permission = $this->transactionContextPermission($context);
            abort_unless($request->user()->hasPermission($permission), 403);
        }
        if ($locationId) {
            $user = $request->user();
            $allowed = $historyContext
                ? $location && $this->locationAccess->canViewLocation($user, $location)
                : $location && $this->locationAccess->canTransactLocation($user, $location);
            abort_unless($allowed, 403);
        }

        if (! $historyContext) {
            abort_unless($this->locationAccess->itemHasTransactionAccess(
                $request->user(),
                (int) $itemId,
                $locationId ? (int) $locationId : null,
                allowUnassociated: $context === 'receipt',
            ), 403);
        }

        if ($context === 'issue') {
            abort_unless(InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') === InventoryMutationMode::ACTIVE, 403);
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
            abort_unless($policy
                && in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                && trim((string) $policy->evidence_reference) !== ''
                && $policy->decided_by_user_id !== null
                && $policy->decided_at !== null, 403);
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                abort_unless($request->query('lot_mode') === 'existing', 403);
            } else {
                abort_unless($request->query('lot_mode') === 'untracked', 403);

                return response()->json([]);
            }
        }

        if ($context === 'transfer') {
            abort_unless(InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') === InventoryMutationMode::ACTIVE, 403);
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
            abort_unless($policy
                && in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                && trim((string) $policy->evidence_reference) !== ''
                && $policy->decided_by_user_id !== null
                && $policy->decided_at !== null, 403);
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                abort_unless($request->query('lot_mode') === 'existing', 403);
            } else {
                abort_unless($request->query('lot_mode') === 'untracked', 403);

                return response()->json([]);
            }
        }

        if ($context === 'material-disposal') {
            abort_unless(InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') === InventoryMutationMode::ACTIVE, 403);
            $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
            abort_unless($policy
                && in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                && trim((string) $policy->evidence_reference) !== ''
                && $policy->decided_by_user_id !== null
                && $policy->decided_at !== null, 403);
            if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                abort_unless($request->query('lot_mode') === 'existing', 403);
            } else {
                abort_unless($request->query('lot_mode') === 'untracked', 403);

                return response()->json([]);
            }
        }

        $lotsQuery = InventoryLot::query()
            ->where('item_id', $itemId)
            ->where('status', '!=', 'DISPOSED');

        if ($historyContext) {
            $this->locationAccess->lotsForView($lotsQuery, $request->user());
        }

        if ($context === 'receipt') {
            $lotsQuery->where('status', 'ACTIVE');
            $lotsQuery->where(function ($lots) use ($location): void {
                $lots->where(function ($unassociatedLots): void {
                    $unassociatedLots->whereDoesntHave('balances')->whereDoesntHave('movements');
                })->orWhereHas('balances', fn ($balances) => $balances->where('location_id', $location->id))
                    ->orWhereHas('movements', function ($movements) use ($location): void {
                        $movements->where('from_location_id', $location->id)
                            ->orWhere('to_location_id', $location->id);
                    });
            });
        } elseif (! $historyContext) {
            $lotsQuery->whereHas('balances', fn ($query) => $location
                ? $query->where('location_id', $location->id)
                : $query->whereIn('location_id', $this->locationAccess->visibleLocationIds($request->user())));
        }

        $lots = $lotsQuery->fefo()
            ->get()
            ->map(function ($lot) {
                return [
                    'id' => $lot->id,
                    'lot_no' => $lot->lot_no,
                    'expiry_date' => $lot->expiry_date?->format('Y-m-d'),
                    'status' => $lot->status,
                    'can_issue' => $lot->canBeIssued(),
                    'can_dispose' => $lot->status !== 'DISPOSED',
                    'is_expired' => $lot->is_expired,
                    'days_until_expiry' => $lot->days_until_expiry,
                ];
            });

        return response()->json($lots);
    }

    public function getBalanceForSelection(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'integer'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'lot_mode' => ['nullable', 'in:existing,untracked'],
            'balance_endpoint' => ['nullable', 'in:source,destination'],
            'from_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
        ]);
        $location = InventoryLocation::query()->find($request->input('location_id'));
        abort_unless($location, 403);
        $item = InventoryItem::query()->whereKey($validated['item_id'])->firstOrFail();

        if ($request->filled('context')) {
            $permission = $this->transactionContextPermission((string) $request->query('context'));
            abort_unless($request->user()->hasPermission($permission), 403);
            abort_unless($this->locationAccess->canTransactLocation($request->user(), $location), 403);
            abort_unless($item->is_active, 403);
            if ($request->query('context') === 'transfer') {
                abort_unless(InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') === InventoryMutationMode::ACTIVE, 403);
                $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
                abort_unless($policy
                    && in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                    && trim((string) $policy->evidence_reference) !== ''
                    && $policy->decided_by_user_id !== null
                    && $policy->decided_at !== null, 403);
                $sourceLocationId = (int) ($validated['from_location_id'] ?? $location->id);
                $sourceLocation = InventoryLocation::query()->find($sourceLocationId);
                abort_unless($sourceLocation && $this->locationAccess->canTransactLocation($request->user(), $sourceLocation), 403);
                $balanceEndpoint = $validated['balance_endpoint'] ?? null;
                abort_unless($balanceEndpoint !== null && $request->filled('from_location_id'), 403);
                abort_unless(
                    ($balanceEndpoint === 'source' && $sourceLocationId === (int) $location->id)
                        || ($balanceEndpoint === 'destination' && $sourceLocationId !== (int) $location->id),
                    403,
                );
                abort_unless($this->locationAccess->itemHasTransactionAccess($request->user(), (int) $validated['item_id'], $sourceLocationId), 403);
                if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                    abort_unless(($validated['lot_mode'] ?? null) === 'existing' && ! empty($validated['lot_id']), 403);
                } else {
                    abort_unless(($validated['lot_mode'] ?? null) === 'untracked' && empty($validated['lot_id']), 403);
                }
            } elseif ($request->query('context') === 'material-disposal') {
                abort_unless(InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') === InventoryMutationMode::ACTIVE, 403);
                $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
                abort_unless($policy
                    && in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                    && trim((string) $policy->evidence_reference) !== ''
                    && $policy->decided_by_user_id !== null
                    && $policy->decided_at !== null, 403);
                if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                    abort_unless(($validated['lot_mode'] ?? null) === 'existing' && ! empty($validated['lot_id']), 403);
                } else {
                    abort_unless(($validated['lot_mode'] ?? null) === 'untracked' && empty($validated['lot_id']), 403);
                }
                abort_unless($this->locationAccess->itemHasTransactionAccess(
                    $request->user(),
                    (int) $validated['item_id'],
                    (int) $location->id,
                ), 403);
            } elseif ($location->is_restricted) {
                abort_unless($this->locationAccess->itemHasTransactionAccess(
                    $request->user(),
                    (int) $validated['item_id'],
                    (int) $location->id,
                    allowUnassociated: $request->query('context') === 'receipt',
                ), 403);
            }
            if ($request->query('context') === 'issue') {
                abort_unless(InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->value('mode') === InventoryMutationMode::ACTIVE, 403);
                $policy = InventoryLotTrackingPolicy::query()->where('item_type', $item->item_type)->first();
                abort_unless($policy
                    && in_array($policy->lot_tracking_mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)
                    && trim((string) $policy->evidence_reference) !== ''
                    && $policy->decided_by_user_id !== null
                    && $policy->decided_at !== null, 403);
                if ($policy->lot_tracking_mode === InventoryLotTrackingPolicy::LOT_TRACKED) {
                    abort_unless($validated['lot_mode'] === 'existing' && ! empty($validated['lot_id']), 403);
                } else {
                    abort_unless($validated['lot_mode'] === 'untracked' && empty($validated['lot_id']), 403);
                }
            }
        } else {
            abort_unless($request->user()->hasPermission('inventori.view'), 403);
            abort_unless($this->locationAccess->canViewLocation($request->user(), $location), 403);
        }

        $lotId = $validated['lot_id'] ?? null;
        if ($lotId !== null) {
            $lot = InventoryLot::query()
                ->whereKey($lotId)
                ->where('item_id', $validated['item_id'])
                ->first();
            abort_unless($lot, 403);
            if ($request->query('context') === 'issue') {
                abort_unless($lot->canBeIssued(), 403);
            }
            if ($request->query('context') === 'transfer') {
                abort_unless($lot->canBeIssued(), 403);
            }
            if ($request->query('context') === 'material-disposal') {
                abort_unless($lot->status !== 'DISPOSED', 403);
            }

            if ($request->query('context') === 'transfer' && $request->query('balance_endpoint') === 'destination') {
                abort_unless($this->locationAccess->canTransactLocation($request->user(), $location), 403);
            } elseif ($location->is_restricted && ! $this->locationAccess->canViewLocation($request->user(), $location)) {
                abort_unless(InventoryBalance::query()->where([
                    'item_id' => $validated['item_id'],
                    'lot_id' => $lotId,
                    'location_id' => $validated['location_id'],
                ])->exists(), 403);
            }
        }

        $balance = InventoryBalance::where([
            'item_id' => $validated['item_id'],
            'lot_id' => $lotId,
            'location_id' => $validated['location_id'],
        ])->first();

        return response()->json([
            'on_hand_qty' => $balance ? (float) $balance->on_hand_qty : 0,
            'available_qty' => $balance ? $balance->available_qty : 0,
            'uom' => $item?->uom,
        ]);
    }

    private function transactionContextPermission(?string $context): string
    {
        return match ($context) {
            'receipt' => 'inventori.receipt.create',
            'issue' => 'inventori.issue.create',
            'transfer' => 'inventori.transfer.create',
            'stocktake' => 'inventori.stocktake.submit',
            'material-disposal' => 'inventori.material-disposal.create',
            default => abort(403),
        };
    }
}
