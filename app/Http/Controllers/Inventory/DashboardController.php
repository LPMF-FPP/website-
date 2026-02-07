<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\Sample;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(): View
    {
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
}
