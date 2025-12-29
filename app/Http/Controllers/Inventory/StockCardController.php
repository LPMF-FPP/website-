<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Services\InventoryMovementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCardController extends Controller
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['item_id', 'lot_id', 'location_id', 'date_from', 'date_to']);
        
        $stockCard = [];
        if ($request->filled('item_id')) {
            $stockCard = $this->movementService->getStockCard($filters);
        }

        $items = InventoryItem::active()->orderBy('name')->get();
        $locations = InventoryLocation::orderBy('name')->get();
        
        $lots = [];
        if ($request->filled('item_id')) {
            $lots = InventoryLot::where('item_id', $request->item_id)
                ->orderBy('lot_no')
                ->get();
        }

        $selectedItem = $request->filled('item_id') 
            ? InventoryItem::find($request->item_id) 
            : null;

        return view('inventory.stock-card', [
            'stockCard' => $stockCard,
            'items' => $items,
            'locations' => $locations,
            'lots' => $lots,
            'filters' => $filters,
            'selectedItem' => $selectedItem,
        ]);
    }
}
