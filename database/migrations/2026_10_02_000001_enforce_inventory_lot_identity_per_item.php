<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX_NAME = 'inventory_lots_item_canonical_no_unique';

    private const CHECK_NAME = 'inventory_lots_canonical_lot_no_nonempty_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Lot identity enforcement requires PostgreSQL; this migration was not applied.');
        }

        DB::statement('LOCK TABLE inventory_lots IN SHARE MODE');

        $collisions = DB::table('inventory_lots')
            ->select('item_id')
            ->selectRaw("BTRIM(lot_no, ' ') AS canonical_lot_no")
            ->selectRaw('COUNT(*) AS lot_count')
            ->groupBy('item_id')
            ->groupByRaw("BTRIM(lot_no, ' ')")
            ->havingRaw("COUNT(*) > 1 OR BTRIM(lot_no, ' ') = ''")
            ->orderBy('item_id')
            ->get();

        if ($collisions->isNotEmpty()) {
            $details = $collisions->map(function (object $collision): string {
                $lots = DB::table('inventory_lots')
                    ->where('item_id', $collision->item_id)
                    ->whereRaw("BTRIM(lot_no, ' ') = ?", [$collision->canonical_lot_no])
                    ->orderBy('id')
                    ->get(['id', 'lot_no', 'status']);
                $records = $lots->map(fn (object $lot): string => '#'.$lot->id.'='.json_encode($lot->lot_no, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).' ('.$lot->status.')')->implode(', ');

                return "item_id={$collision->item_id}, canonical=".json_encode($collision->canonical_lot_no).", records=[{$records}]";
            })->implode("\n");

            throw new RuntimeException("Invalid or colliding inventory lot identities block the unique-index rollout. Resolve each record without rewriting history, then rerun this migration:\n{$details}");
        }

        DB::statement('ALTER TABLE inventory_lots ADD CONSTRAINT '.self::CHECK_NAME." CHECK (BTRIM(lot_no, ' ') <> '')");
        DB::statement('CREATE UNIQUE INDEX '.self::INDEX_NAME." ON inventory_lots (item_id, (BTRIM(lot_no, ' ')))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
            DB::statement('ALTER TABLE inventory_lots DROP CONSTRAINT IF EXISTS '.self::CHECK_NAME);
        }
    }
};
