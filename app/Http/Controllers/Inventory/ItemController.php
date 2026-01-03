<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View
    {
        $query = InventoryItem::query()
            ->withCount('lots')
            ->with('balances');

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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%")
                    ->orWhere('manufacturer', 'ilike', "%{$search}%");
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateItem($request);

        InventoryItem::create($validated);

        return redirect()
            ->route('inventory.items.index')
            ->with('success', 'Item berhasil ditambahkan.');
    }

    public function edit(InventoryItem $item): View
    {
        return view('inventory.items.form', [
            'item' => $item,
            'itemTypes' => InventoryItem::ITEM_TYPES,
            'storageConditions' => InventoryItem::STORAGE_CONDITIONS,
        ]);
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $this->validateItem($request, $item->id);

        $item->update($validated);

        return redirect()
            ->route('inventory.items.index')
            ->with('success', 'Item berhasil diperbarui.');
    }

    protected function validateItem(Request $request, ?int $itemId = null): array
    {
        return $request->validate([
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
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Force delete an inventory item (Admin only).
     * This will delete ALL related data (movements, balances, lots).
     */
    public function destroy(InventoryItem $item): RedirectResponse
    {
        // Only admin can delete items
        if (auth()->user()->role !== 'admin') {
            abort(Response::HTTP_FORBIDDEN, 'Hanya admin yang dapat menghapus item.');
        }

        $itemName = $item->name;

        // Force delete: Remove all related data first
        // 1. Delete all movements for this item's lots
        $lotIds = $item->lots()->pluck('id');
        \App\Models\InventoryMovement::whereIn('lot_id', $lotIds)->delete();

        // 2. Delete all balances for this item
        $item->balances()->delete();

        // 3. Delete all lots for this item
        $item->lots()->delete();

        // 4. Delete the item itself
        $item->delete();

        return redirect()
            ->route('inventory.items.index')
            ->with('success', "Item '{$itemName}' dan semua data terkait berhasil dihapus.");
    }
}
