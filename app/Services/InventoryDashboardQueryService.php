<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InventoryDashboardQueryService
{
    public const CATEGORIES = ['low_stock' => 'Stok rendah', 'near_expiry' => 'Hampir kedaluwarsa (30 hari)', 'expired' => 'Kedaluwarsa'];

    public function __construct(private readonly InventoryLocationAccessService $access) {}

    public function query(string $category, User $viewer): Builder
    {
        $visibleIds = $this->access->visibleLocationIds($viewer);
        if ($category === 'low_stock') {
            return $this->access->itemsForView(InventoryItem::query(), $viewer)->active()
                ->whereRaw('(SELECT COALESCE(SUM(on_hand_qty), 0) FROM inventory_balances WHERE item_id = inventory_items.id AND location_id IN ('.(clone $visibleIds)->toSql().')) < min_stock', (clone $visibleIds)->getBindings())
                ->with(['balances' => fn ($balances) => $balances->whereIn('location_id', $visibleIds)])
                ->withSum(['movements as monthly_usage' => fn ($movements) => $movements->where('movement_type', 'ISSUE')->where('performed_at', '>=', now()->subDays(30))->whereIn('from_location_id', $visibleIds)], 'qty')
                ->orderBy('name')->orderBy('id');
        }

        abort_unless(in_array($category, ['near_expiry', 'expired'], true), 404);

        return $this->access->lotsForView(InventoryLot::query(), $viewer)
            ->when($category === 'near_expiry', fn ($lots) => $lots->nearExpiry(30), fn ($lots) => $lots->expired())
            ->where('status', '!=', 'DISPOSED')
            ->whereHas('balances', fn ($balances) => $balances->where('on_hand_qty', '>', 0)->whereIn('location_id', $visibleIds))
            ->with(['item', 'balances' => fn ($balances) => $balances->whereIn('location_id', $visibleIds)->with('location')])
            ->orderBy('expiry_date')->orderBy('id');
    }
}
