<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockCardRequest;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Services\InventoryLocationAccessService;
use App\Services\InventoryStockCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class StockCardController extends Controller
{
    public function __construct(
        private readonly InventoryStockCardService $reports,
        private readonly InventoryLocationAccessService $locationAccess,
    ) {
        $this->middleware(['auth', 'verified']);
    }

    public function index(StockCardRequest $request): View
    {
        $report = $this->reports->report($request->filters(), $request->user());
        $filters = $report['filters'];
        $page = (int) $request->validated('page', 1);
        $rows = $report['item'] ? $report['rows'] : [];
        $pagination = new LengthAwarePaginator(array_slice($rows, ($page - 1) * 50, 50), count($rows), 50, $page, ['path' => route('inventory.stock-card'), 'query' => $filters]);

        return view('inventory.stock-card', [
            'report' => $report,
            'stockCard' => $pagination->items(),
            'pagination' => $pagination,
            'items' => $this->locationAccess->itemsForView(InventoryItem::query(), $request->user())->orderBy('name')->get(),
            'locations' => $this->locationAccess->locationsForView(InventoryLocation::query(), $request->user())->orderBy('name')->get(),
            'lots' => ! empty($filters['item_id']) ? $this->locationAccess->lotsForView(InventoryLot::query()->where('item_id', $filters['item_id']), $request->user())->orderBy('lot_no')->get() : [],
            'filters' => $filters,
            'selectedItem' => $report['item'],
        ]);
    }

    public function print(StockCardRequest $request): Response
    {
        $report = $this->reports->report($request->filters(), $request->user());
        abort_unless($report['item'], 422, 'Pilih item untuk mencetak kartu stok.');
        $item = $report['item'];
        $pdf = Pdf::loadView('inventory.pdf.stock-card', [
            'report' => $report,
            'stockCard' => $report['rows'],
            'item' => $item,
            'lot' => $report['lot'],
            'location' => $report['location'],
            'filters' => $report['filters'],
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
        ])->setPaper('A4', 'landscape');
        $identity = preg_replace('/[^a-z0-9._-]/i', '-', $item->item_code ?: 'item-'.$item->id);

        return $pdf->stream('kartu-stok-'.strtolower($identity).'-'.now()->format('Ymd').'.pdf');
    }
}
