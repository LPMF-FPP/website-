<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Sample;
use App\Models\SampleDisposal;
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
            ->belowMinStock()
            ->with('balances')
            ->withSum(['movements as monthly_usage' => function ($query) {
                $query->where('movement_type', 'ISSUE')
                    ->where('performed_at', '>=', now()->subDays(30));
            }], 'qty')
            ->take(10)
            ->get();

        // Calculate usage trend for low stock items
        foreach ($lowStockItems as $item) {
            // monthly_usage is already loaded via withSum
            $monthlyUsage = $item->monthly_usage ?? 0;

            if ($item->min_stock > 0 && $monthlyUsage > ($item->min_stock * 2)) {
                $item->trend = 'high';
            } elseif ($monthlyUsage > 0) {
                $item->trend = 'moderate';
            } else {
                $item->trend = 'low';
            }
        }

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

        $finishedSamplesCount = Sample::query()
            ->whereNotNull('testing_completed_at')
            ->whereIn('disposal_status', ['pending', 'eligible'])
            ->count();

        $disposedThisMonthCount = Sample::query()
            ->where('disposal_status', 'disposed')
            ->whereNotNull('disposed_at')
            ->where('disposed_at', '>=', now()->startOfMonth())
            ->count();

        $recentSampleDisposals = SampleDisposal::query()
            ->withCount('samples')
            ->latest('executed_at')
            ->take(5)
            ->get();

        $topMovers = InventoryMovement::query()
            ->where('movement_type', 'ISSUE')
            ->where('performed_at', '>=', now()->subDays(7))
            ->selectRaw('item_id, SUM(qty) as total_qty')
            ->groupBy('item_id')
            ->orderByDesc('total_qty')
            ->with('item:id,name,uom')
            ->take(5)
            ->get();

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
            'finishedSamplesCount' => $finishedSamplesCount,
            'disposedThisMonthCount' => $disposedThisMonthCount,
            'recentSampleDisposals' => $recentSampleDisposals,
            'topMovers' => $topMovers,
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

    public function ajaxOverview(Request $request): JsonResponse
    {
        $query = InventoryItem::query()
            ->active()
            ->withSum('balances as total_stock', 'on_hand_qty')
            ->with(['balances.location', 'lots' => function ($q) {
                $q->where('status', 'ACTIVE')->orderBy('expiry_date');
            }]);

        if ($request->has('q') && $request->q !== '') {
            $q = strtolower($request->q);
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%']);
        }

        // Sort by total stock descending by default so users see what they HAVE first
        // COALESCE is important because items with no balances return NULL for total_stock,
        // which sorts unexpectedly depending on DB driver. We want 0 at the bottom.
        $query->orderByRaw('COALESCE((SELECT SUM(on_hand_qty) FROM inventory_balances WHERE item_id = inventory_items.id), 0) DESC')
            ->orderBy('name');

        $items = $query->paginate(10);

        // Append status field
        $items->getCollection()->transform(function ($item) {
            $item->status = 'ok';
            $totalStock = (float) $item->total_stock;

            if ($totalStock <= 0) {
                $item->status = 'empty';
            } elseif ($totalStock <= $item->min_stock) {
                $item->status = 'critical';
            }

            // Explicitly set total_stock
            $item->total_stock = $totalStock;

            return $item;
        });

        return response()->json($items);
    }

    public function ajaxFastMoving(Request $request): JsonResponse
    {
        $query = InventoryMovement::query()
            ->where('movement_type', 'ISSUE')
            ->where('performed_at', '>=', now()->subDays(30))
            ->selectRaw('item_id, SUM(qty) as total_out')
            ->groupBy('item_id')
            ->orderByDesc('total_out')
            ->with('item:id,name,uom');

        return response()->json($query->paginate(10));
    }
}
