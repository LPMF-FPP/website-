<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TransactionController extends Controller
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {
        $this->middleware(['auth', 'verified']);
    }

    // ==================== RECEIPT ====================

    public function receiptForm(): View
    {
        return view('inventory.transactions.receipt', [
            'items' => InventoryItem::active()->orderBy('name')->get(),
            'locations' => InventoryLocation::orderBy('name')->get(),
            'referenceTypes' => InventoryMovement::REFERENCE_TYPES,
        ]);
    }

    public function receiptSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'location_id' => ['nullable', 'exists:inventory_locations,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'reference_type' => ['nullable', 'string'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            // New lot fields
            'new_lot_no' => ['nullable', 'string', 'max:100'],
            'new_lot_expiry' => ['nullable', 'date'],
            // New location fields
            'location_mode' => ['nullable', 'string', 'in:existing,new'],
            'new_location_name' => ['nullable', 'string', 'max:255'],
            'new_location_type' => ['nullable', 'string', 'in:storage,lab,cold_room,quarantine'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);

        // Determine location ID - create new location if needed
        $locationId = $validated['location_id'] ?? null;
        $locationMode = $validated['location_mode'] ?? 'existing';

        if ($locationMode === 'new' && ! empty($validated['new_location_name'])) {
            // Check if location with same name already exists
            $existingLocation = InventoryLocation::where('name', $validated['new_location_name'])->first();
            
            if ($existingLocation) {
                $locationId = $existingLocation->id;
            } else {
                $location = InventoryLocation::create([
                    'name' => $validated['new_location_name'],
                    'location_type' => $validated['new_location_type'] ?? 'storage',
                    'is_active' => true,
                ]);
                $locationId = $location->id;
            }
        }

        // Validate that we have a location
        if (! $locationId) {
            return back()
                ->withInput()
                ->withErrors(['location_id' => 'Lokasi penyimpanan wajib dipilih atau diisi.']);
        }

        // Create new lot if lot_no provided
        $lotId = $validated['lot_id'] ?? null;
        if (! empty($validated['new_lot_no'])) {
            // Check expiry requirement
            if ($item->requiresExpiry() && empty($validated['new_lot_expiry'])) {
                return back()
                    ->withInput()
                    ->withErrors(['new_lot_expiry' => 'Tanggal kadaluarsa wajib diisi untuk jenis item ini.']);
            }

            $lot = InventoryLot::create([
                'item_id' => $validated['item_id'],
                'lot_no' => $validated['new_lot_no'],
                'expiry_date' => $validated['new_lot_expiry'] ?? null,
                'received_date' => now()->toDateString(),
                'status' => 'ACTIVE',
            ]);
            $lotId = $lot->id;
        }

        try {
            $this->movementService->receipt([
                'item_id' => $validated['item_id'],
                'lot_id' => $lotId,
                'location_id' => $locationId,
                'qty' => $validated['qty'],
                'uom' => $item->uom,
                'reference_type' => $validated['reference_type'] ?? 'MANUAL',
                'unit_cost' => $validated['unit_cost'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()
                ->route('inventory.transaction.receipt')
                ->with('success', 'Penerimaan berhasil dicatat.');
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== ISSUE ====================

    public function issueForm(): View
    {
        return view('inventory.transactions.issue', [
            'items' => InventoryItem::active()->orderBy('name')->get(),
            'locations' => InventoryLocation::orderBy('name')->get(),
            'referenceTypes' => InventoryMovement::REFERENCE_TYPES,
        ]);
    }

    public function issueSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'location_id' => ['required', 'exists:inventory_locations,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'reference_type' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);

        try {
            $this->movementService->issue([
                'item_id' => $validated['item_id'],
                'lot_id' => $validated['lot_id'] ?? null,
                'location_id' => $validated['location_id'],
                'qty' => $validated['qty'],
                'uom' => $item->uom,
                'reference_type' => $validated['reference_type'] ?? 'MANUAL',
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()
                ->route('inventory.transaction.issue')
                ->with('success', 'Pengeluaran berhasil dicatat.');
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
            'items' => InventoryItem::active()->orderBy('name')->get(),
            'locations' => InventoryLocation::orderBy('name')->get(),
        ]);
    }

    public function transferSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'from_location_id' => ['required', 'exists:inventory_locations,id'],
            'to_location_id' => ['required', 'exists:inventory_locations,id', 'different:from_location_id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);

        try {
            $this->movementService->transfer([
                'item_id' => $validated['item_id'],
                'lot_id' => $validated['lot_id'] ?? null,
                'from_location_id' => $validated['from_location_id'],
                'to_location_id' => $validated['to_location_id'],
                'qty' => $validated['qty'],
                'uom' => $item->uom,
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()
                ->route('inventory.transaction.transfer')
                ->with('success', 'Transfer berhasil dicatat.');
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== STOCKTAKE ====================

    public function stocktakeForm(): View
    {
        return view('inventory.transactions.stocktake', [
            'items' => InventoryItem::active()->orderBy('name')->get(),
            'locations' => InventoryLocation::orderBy('name')->get(),
        ]);
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
            $results = $this->movementService->processStocktake($validated['rows']);

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
            'items' => InventoryItem::active()->orderBy('name')->get(),
            'locations' => InventoryLocation::orderBy('name')->get(),
            'reasonCodes' => InventoryMovement::REASON_CODES,
        ]);
    }

    public function disposeSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_id' => ['nullable', 'exists:inventory_lots,id'],
            'location_id' => ['required', 'exists:inventory_locations,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'reason_code' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);

        try {
            $this->movementService->dispose([
                'item_id' => $validated['item_id'],
                'lot_id' => $validated['lot_id'] ?? null,
                'location_id' => $validated['location_id'],
                'qty' => $validated['qty'],
                'uom' => $item->uom,
                'reason_code' => $validated['reason_code'],
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()
                ->route('inventory.transaction.dispose')
                ->with('success', 'Disposal berhasil dicatat.');
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ==================== AJAX HELPERS ====================

    public function getLotsForItem(Request $request)
    {
        $itemId = $request->input('item_id');

        $lots = InventoryLot::where('item_id', $itemId)
            ->where('status', '!=', 'DISPOSED')
            ->fefo()
            ->get()
            ->map(function ($lot) {
                return [
                    'id' => $lot->id,
                    'lot_no' => $lot->lot_no,
                    'expiry_date' => $lot->expiry_date?->format('Y-m-d'),
                    'status' => $lot->status,
                    'can_issue' => $lot->canBeIssued(),
                    'is_expired' => $lot->is_expired,
                    'days_until_expiry' => $lot->days_until_expiry,
                ];
            });

        return response()->json($lots);
    }

    public function getBalanceForSelection(Request $request)
    {
        $balance = InventoryBalance::where([
            'item_id' => $request->input('item_id'),
            'lot_id' => $request->input('lot_id') ?: null,
            'location_id' => $request->input('location_id'),
        ])->first();

        return response()->json([
            'on_hand_qty' => $balance ? (float) $balance->on_hand_qty : 0,
            'available_qty' => $balance ? $balance->available_qty : 0,
        ]);
    }
}
