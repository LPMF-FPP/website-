<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LotController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(InventoryItem $item): View
    {
        $lots = InventoryLot::query()
            ->where('item_id', $item->id)
            ->with(['balances.location'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('inventory.items.lots', [
            'item' => $item,
            'lots' => $lots,
            'statuses' => InventoryLot::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'lot_no' => ['required', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        // Check if item requires expiry
        $item = InventoryItem::findOrFail($validated['item_id']);
        if ($item->requiresExpiry() && empty($validated['expiry_date'])) {
            return back()
                ->withInput()
                ->withErrors(['expiry_date' => 'Tanggal kadaluarsa wajib diisi untuk jenis item ini.']);
        }

        $validated['status'] = 'ACTIVE';

        InventoryLot::create($validated);

        return redirect()
            ->route('inventory.items.lots', $item)
            ->with('success', 'Lot berhasil ditambahkan.');
    }
}
