<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->string('lot_mode', 24)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_lot_mode_check CHECK (lot_mode IS NULL OR lot_mode IN ('tracked_existing', 'tracked_new', 'untracked'))");
            DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_stock_movement_lot_mode_tuple_check CHECK (lot_mode IS NULL OR (movement_type IN ('RECEIPT', 'ISSUE') AND ((lot_mode = 'untracked' AND lot_id IS NULL) OR (lot_mode = 'tracked_existing' AND lot_id IS NOT NULL) OR (lot_mode = 'tracked_new' AND movement_type = 'RECEIPT' AND lot_id IS NOT NULL))))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS inventory_stock_movement_lot_mode_tuple_check');
            DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS inventory_movements_lot_mode_check');
        }

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('lot_mode');
        });
    }
};
