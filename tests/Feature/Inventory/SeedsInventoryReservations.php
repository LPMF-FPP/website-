<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use Illuminate\Support\Facades\DB;

trait SeedsInventoryReservations
{
    /** @param array<string, mixed> $attributes */
    protected function createInventoryBalanceWithReservedFixture(array $attributes): InventoryBalance
    {
        if (DB::getDriverName() !== 'pgsql' || (float) ($attributes['reserved_qty'] ?? 0) === 0.0) {
            return InventoryBalance::query()->create($attributes);
        }

        $triggerExists = DB::table('pg_trigger')
            ->join('pg_class', 'pg_class.oid', '=', 'pg_trigger.tgrelid')
            ->where('pg_class.relname', 'inventory_balances')
            ->where('pg_trigger.tgname', 'inventory_balances_reserved_qty_write_guard')
            ->where('pg_trigger.tgisinternal', false)
            ->exists();
        if (! $triggerExists) {
            return InventoryBalance::query()->create($attributes);
        }

        DB::statement('ALTER TABLE inventory_balances DISABLE TRIGGER inventory_balances_reserved_qty_write_guard');
        try {
            return InventoryBalance::query()->create($attributes);
        } finally {
            DB::statement('ALTER TABLE inventory_balances ENABLE TRIGGER inventory_balances_reserved_qty_write_guard');
        }
    }
}
