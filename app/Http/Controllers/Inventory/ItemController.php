<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\InventoryItemIdentityService;
use App\Services\InventoryItemLifecycleService;
use App\Services\InventoryLocationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function __construct(
        private readonly InventoryLocationAccessService $locationAccess,
        private readonly InventoryItemIdentityService $identityService,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View
    {
        $visibleLocations = $this->locationAccess->visibleLocationIds($request->user());
        $query = $this->locationAccess->itemsForView(InventoryItem::query(), $request->user())
            ->withCount(['lots' => fn ($lots) => $lots->whereHas('balances', fn ($balances) => $balances->whereIn('location_id', $visibleLocations))])
            ->with(['balances' => fn ($balances) => $balances->whereIn('location_id', $this->locationAccess->visibleLocationIds($request->user()))]);

        // Filter by type
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        // Filter by active status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        } else {
            $query->active();
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $itemCodeSearch = InventoryItemIdentityService::canonicalize($search);
            $query->where(function ($q) use ($search, $itemCodeSearch) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%")
                    ->orWhere('manufacturer', 'ilike', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$itemCodeSearch}%");
            });
        }

        $items = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('inventory.items.index', [
            'items' => $items,
            'itemTypes' => InventoryItem::ITEM_TYPES,
            'filters' => $request->only(['type', 'status', 'search']),
        ]);
    }

    public function create(): View
    {
        return view('inventory.items.form', [
            'item' => new InventoryItem,
            'itemTypes' => InventoryItem::ITEM_TYPES,
            'storageConditions' => InventoryItem::STORAGE_CONDITIONS,
            'identityHasHistory' => false,
            'lifecycleEvents' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateItem($request);

        $this->identityService->create($validated, $request->user());

        return redirect()
            ->route('inventory.items.index')
            ->with('success', 'Item berhasil ditambahkan.');
    }

    public function show(InventoryItem $item): RedirectResponse
    {
        abort_unless($this->locationAccess->itemsForView(InventoryItem::query(), request()->user())->whereKey($item->id)->exists(), 404);

        return redirect()->route('inventory.items.edit', $item);
    }

    public function edit(InventoryItem $item): View
    {
        abort_unless($this->locationAccess->itemsForView(InventoryItem::query(), request()->user())->whereKey($item->id)->exists(), 404);

        return view('inventory.items.form', [
            'item' => $item,
            'itemTypes' => InventoryItem::ITEM_TYPES,
            'storageConditions' => InventoryItem::STORAGE_CONDITIONS,
            'identityHasHistory' => $this->identityService->hasHistory($item),
            'lifecycleEvents' => $item->lifecycleEvents()->with('actor:id,name')->latest('occurred_at')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        abort_unless(
            $this->locationAccess->itemsForView(InventoryItem::query(), $request->user())->whereKey($item->id)->exists(),
            404,
        );

        $validated = $this->validateItem($request, $item->id);
        $reason = $request->validate([
            'identity_change_reason' => ['nullable', 'string', 'max:2000'],
        ])['identity_change_reason'] ?? null;

        $this->identityService->update($item->id, $validated, $request->user(), $reason);

        return redirect()
            ->route('inventory.items.index')
            ->with('success', 'Item berhasil diperbarui.');
    }

    public function archive(Request $request, InventoryItem $item, InventoryItemLifecycleService $lifecycleService): RedirectResponse
    {
        $this->authorizeVisibleItem($request, $item);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $changed = $lifecycleService->archive($item->id, $request->user(), $validated['reason'] ?? null);

        return redirect()
            ->route('inventory.items.index', ['status' => 'inactive'])
            ->with('success', $changed ? 'Item berhasil diarsipkan.' : 'Item sudah diarsipkan.');
    }

    public function restore(Request $request, InventoryItem $item, InventoryItemLifecycleService $lifecycleService): RedirectResponse
    {
        $this->authorizeVisibleItem($request, $item);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $changed = $lifecycleService->restore($item->id, $request->user(), $validated['reason'] ?? null);

        return redirect()
            ->route('inventory.items.index')
            ->with('success', $changed ? 'Item berhasil dipulihkan ke daftar aktif.' : 'Item sudah aktif.');
    }

    protected function validateItem(Request $request, ?int $itemId = null): array
    {
        $submittedCode = $request->input('item_code');
        if (is_string($submittedCode)) {
            $request->merge(['item_code' => InventoryItemIdentityService::canonicalize($submittedCode)]);
        }

        $uniqueCode = \Illuminate\Validation\Rule::unique('inventory_items', 'item_code');
        if ($itemId !== null) {
            $uniqueCode->ignore($itemId);
        }

        return $request->validate([
            'item_code' => ['required', 'string', 'max:64', 'regex:/\A[A-Z0-9._-]+\z/', $uniqueCode],
            'item_type' => ['required', 'in:REAGENT,CONSUMABLE,STANDARD,CONTROL,OTHER'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'specification' => ['nullable', 'string'],
            'uom' => ['required', 'string', 'max:50'],
            'pack_size' => ['nullable', 'numeric', 'min:0'],
            'is_hazardous' => ['boolean'],
            'hazard_class' => ['nullable', 'string', 'max:100'],
            'storage_condition' => ['nullable', 'string', 'max:50'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function authorizeVisibleItem(Request $request, InventoryItem $item): void
    {
        abort_unless(
            $this->locationAccess->itemsForView(InventoryItem::query(), $request->user())->whereKey($item->id)->exists(),
            404,
        );
    }

    /** Permanently delete an item only when it has no inventory or identity history. */
    public function destroy(InventoryItem $item): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('inventori.delete'), 403);
        $this->authorizeVisibleItem(request(), $item);

        $deleted = DB::transaction(function () use ($item): bool {
            $lockedItem = InventoryItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->hasHistoricalRecords()) {
                return false;
            }

            $lockedItem->delete();

            return true;
        });

        if (! $deleted) {
            return redirect()
                ->route('inventory.items.index')
                ->with('error', 'Item memiliki riwayat dan tidak dapat dihapus. Arsipkan item untuk mempertahankan audit.');
        }

        return redirect()
            ->route('inventory.items.index')
            ->with('success', 'Item tanpa riwayat berhasil dihapus.');
    }
}
