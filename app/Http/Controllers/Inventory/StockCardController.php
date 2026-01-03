<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Services\InventoryMovementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /**
     * Generate PDF for stock card.
     */
    public function print(Request $request): Response
    {
        $request->validate([
            'item_id' => 'required|exists:inventory_items,id',
        ]);

        $filters = $request->only(['item_id', 'lot_id', 'location_id', 'date_from', 'date_to']);
        $stockCard = $this->movementService->getStockCard($filters);

        $item = InventoryItem::findOrFail($filters['item_id']);

        $lot = null;
        if (! empty($filters['lot_id'])) {
            $lot = InventoryLot::find($filters['lot_id']);
        }

        $location = null;
        if (! empty($filters['location_id'])) {
            $location = InventoryLocation::find($filters['location_id']);
        }

        $pdf = Pdf::loadView('inventory.pdf.stock-card', [
            'stockCard' => $stockCard,
            'item' => $item,
            'lot' => $lot,
            'location' => $location,
            'filters' => $filters,
            'generatedAt' => now(),
            'generatedBy' => auth()->user(),
        ]);

        $pdf->setPaper('A4', 'landscape');

        $filename = 'kartu-stok-'.str_replace(' ', '-', strtolower($item->name)).'-'.now()->format('Ymd').'.pdf';

        return $pdf->stream($filename);
    }
}
