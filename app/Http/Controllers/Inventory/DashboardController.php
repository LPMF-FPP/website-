<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\Sample;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(): View
    {
        $quickActionItems = InventoryItem::query()
            ->active()
            ->orderBy('name')
            ->take(200)
            ->get(['id', 'name', 'uom']);

        $locations = InventoryLocation::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Low stock items
        $lowStockItems = InventoryItem::query()
            ->active()
            ->with('balances')
            ->get()
            ->filter(fn ($item) => $item->is_below_min_stock)
            ->take(10);

        // Near expiry lots (30 days)
        $nearExpiry30 = InventoryLot::query()
            ->with(['item', 'balances.location'])
            ->nearExpiry(30)
            ->whereHas('balances', fn ($q) => $q->where('on_hand_qty', '>', 0))
            ->orderBy('expiry_date')
            ->take(10)
            ->get();

        // Near expiry lots (60 days)
        $nearExpiry60 = InventoryLot::query()
            ->with(['item', 'balances.location'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', Carbon::today()->addDays(30))
            ->where('expiry_date', '<=', Carbon::today()->addDays(60))
            ->where('status', '!=', 'DISPOSED')
            ->whereHas('balances', fn ($q) => $q->where('on_hand_qty', '>', 0))
            ->orderBy('expiry_date')
            ->take(10)
            ->get();

        // Expired lots with stock
        $expiredLots = InventoryLot::query()
            ->with(['item', 'balances.location'])
            ->expired()
            ->whereHas('balances', fn ($q) => $q->where('on_hand_qty', '>', 0))
            ->orderBy('expiry_date')
            ->take(10)
            ->get();

        // Summary stats
        $totalItems = InventoryItem::active()->count();
        $totalLots = InventoryLot::where('status', 'ACTIVE')->count();
        $lowStockCount = $lowStockItems->count();
        $expiredCount = $expiredLots->count();
        $eligibleSamplesCount = Sample::eligibleForDisposal()->count();

        return view('inventory.dashboard', [
            'quickActionItems' => $quickActionItems,
            'locations' => $locations,
            'lowStockItems' => $lowStockItems,
            'nearExpiry30' => $nearExpiry30,
            'nearExpiry60' => $nearExpiry60,
            'expiredLots' => $expiredLots,
            'stats' => [
                'total_items' => $totalItems,
                'total_lots' => $totalLots,
                'low_stock' => $lowStockCount,
                'expired' => $expiredCount,
            ],
            'eligibleSamplesCount' => $eligibleSamplesCount,
        ]);
    }

    public function ajaxSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([
                'query' => $q,
                'exact_match' => null,
                'results' => [],
            ]);
        }

        $qLower = strtolower($q);

        $exactLots = InventoryLot::query()
            ->with('item')
            ->whereRaw('LOWER(lot_no) = ?', [$qLower])
            ->where('status', '!=', 'DISPOSED')
            ->limit(2)
            ->get();

        $exactMatch = null;
        if ($exactLots->count() === 1) {
            $lot = $exactLots->first();

            $exactMatch = [
                'type' => 'lot',
                'id' => $lot->id,
                'item_id' => $lot->item_id,
                'lot_id' => $lot->id,
                'label' => ($lot->item?->name ?? 'Item').' · '.$lot->lot_no,
                'issue_url' => route('inventory.transaction.issue', ['item_id' => $lot->item_id, 'lot_id' => $lot->id]),
            ];
        }

        if ($exactMatch === null) {
            $exactItems = InventoryItem::query()
                ->active()
                ->whereRaw('LOWER(name) = ?', [$qLower])
                ->limit(2)
                ->get();

            if ($exactItems->count() === 1) {
                $item = $exactItems->first();
                $exactMatch = [
                    'type' => 'item',
                    'id' => $item->id,
                    'item_id' => $item->id,
                    'lot_id' => null,
                    'label' => $item->name,
                    'issue_url' => route('inventory.transaction.issue', ['item_id' => $item->id]),
                ];
            }
        }

        $lots = InventoryLot::query()
            ->with('item')
            ->whereRaw('LOWER(lot_no) LIKE ?', ['%'.$qLower.'%'])
            ->where('status', '!=', 'DISPOSED')
            ->orderBy('lot_no')
            ->limit(10)
            ->get()
            ->map(fn ($lot) => [
                'type' => 'lot',
                'id' => $lot->id,
                'item_id' => $lot->item_id,
                'lot_id' => $lot->id,
                'label' => ($lot->item?->name ?? 'Item').' · '.$lot->lot_no,
                'meta' => $lot->expiry_date ? 'exp '.$lot->expiry_date->format('Y-m-d') : null,
                'issue_url' => route('inventory.transaction.issue', ['item_id' => $lot->item_id, 'lot_id' => $lot->id]),
            ])
            ->values();

        $items = InventoryItem::query()
            ->active()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$qLower.'%'])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'uom'])
            ->map(fn ($item) => [
                'type' => 'item',
                'id' => $item->id,
                'item_id' => $item->id,
                'lot_id' => null,
                'label' => $item->name,
                'meta' => $item->uom,
                'issue_url' => route('inventory.transaction.issue', ['item_id' => $item->id]),
            ])
            ->values();

        return response()->json([
            'query' => $q,
            'exact_match' => $exactMatch,
            'results' => $lots->concat($items)->values(),
        ]);
    }
}
