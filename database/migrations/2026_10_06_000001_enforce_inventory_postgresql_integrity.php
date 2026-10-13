<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NULL_LOT_INDEX = 'inventory_balances_null_lot_tuple_unique';

    private const BALANCE_QTY_CHECK = 'inventory_balances_on_hand_reserved_check';

    private const MOVEMENT_QTY_CHECK = 'inventory_movements_positive_qty_check';

    private const MOVEMENT_ENDPOINT_CHECK = 'inventory_movements_endpoint_shape_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Inventory ledger and balance integrity requires PostgreSQL.');
        }

        DB::statement('LOCK TABLE inventory_balances, inventory_movements IN SHARE ROW EXCLUSIVE MODE');
        $this->assertLegacyDataIsValid();

        DB::statement('CREATE UNIQUE INDEX '.self::NULL_LOT_INDEX.' ON inventory_balances (item_id, location_id) WHERE lot_id IS NULL');
        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT '.self::BALANCE_QTY_CHECK.' CHECK (on_hand_qty >= 0 AND reserved_qty >= 0 AND reserved_qty <= on_hand_qty)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT '.self::MOVEMENT_QTY_CHECK.' CHECK (qty > 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_endpoint_shape_check
            CHECK (
                movement_type = 'RETURN'
                OR (movement_type = 'RECEIPT' AND from_location_id IS NULL AND to_location_id IS NOT NULL)
                OR (movement_type IN ('ISSUE', 'DISPOSE') AND from_location_id IS NOT NULL AND to_location_id IS NULL)
                OR (movement_type = 'TRANSFER' AND from_location_id IS NOT NULL AND to_location_id IS NOT NULL AND from_location_id <> to_location_id)
                OR (movement_type = 'ADJUST' AND ((from_location_id IS NULL AND to_location_id IS NOT NULL) OR (from_location_id IS NOT NULL AND to_location_id IS NULL)))
            )
            SQL);

        Schema::create('inventory_integrity_audits', function ($table): void {
            $table->id();
            $table->text('snapshot_id');
            $table->timestampTz('audited_at');
            $table->string('status', 16);
            $table->unsignedBigInteger('tuple_count');
            $table->unsignedBigInteger('drift_count');
            $table->timestamps();
            $table->index(['audited_at', 'status'], 'inventory_integrity_audits_status_index');
        });

        Schema::create('inventory_integrity_audit_lines', function ($table): void {
            $table->id();
            $table->foreignId('audit_id')->constrained('inventory_integrity_audits')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->boolean('balance_existed');
            $table->decimal('ledger_qty', 30, 3);
            $table->decimal('balance_qty', 30, 3);
            $table->decimal('delta_qty', 30, 3);
            $table->timestampTz('created_at');
            $table->index(['audit_id', 'item_id', 'location_id'], 'inventory_integrity_audit_lines_tuple_index');
        });

        DB::statement("ALTER TABLE inventory_integrity_audits ADD CONSTRAINT inventory_integrity_audits_status_check CHECK (status IN ('clean', 'drift_detected'))");
        $this->installPostgresTriggers();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_append_only ON inventory_movements');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_no_truncate ON inventory_movements');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_return_insert_block ON inventory_movements');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_balances_reserved_qty_write_guard ON inventory_balances');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_integrity_audits_append_only ON inventory_integrity_audits');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_integrity_audits_no_truncate ON inventory_integrity_audits');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_integrity_audit_lines_append_only ON inventory_integrity_audit_lines');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_integrity_audit_lines_no_truncate ON inventory_integrity_audit_lines');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_movement_mutation()');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_inventory_return_insert()');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_reserved_qty_write()');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_integrity_audit_mutation()');

        DB::statement('ALTER TABLE inventory_balances DROP CONSTRAINT IF EXISTS '.self::BALANCE_QTY_CHECK);
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS '.self::MOVEMENT_QTY_CHECK);
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS '.self::MOVEMENT_ENDPOINT_CHECK);
        DB::statement('DROP INDEX IF EXISTS '.self::NULL_LOT_INDEX);
        Schema::dropIfExists('inventory_integrity_audit_lines');
        Schema::dropIfExists('inventory_integrity_audits');
    }

    private function assertLegacyDataIsValid(): void
    {
        $duplicateNullTuples = DB::table('inventory_balances')
            ->select('item_id', 'location_id')
            ->selectRaw('COUNT(*) AS tuple_count')
            ->whereNull('lot_id')
            ->groupBy('item_id', 'location_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('item_id')
            ->orderBy('location_id')
            ->limit(20)
            ->get();

        $invalidBalances = DB::table('inventory_balances')
            ->whereRaw('on_hand_qty < 0 OR reserved_qty < 0 OR reserved_qty > on_hand_qty')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'item_id', 'lot_id', 'location_id', 'on_hand_qty', 'reserved_qty']);

        $invalidMovements = DB::table('inventory_movements')
            ->where(function ($query): void {
                $query->where('qty', '<=', 0)
                    ->orWhere(function ($shape): void {
                        $shape->where('movement_type', 'RECEIPT')
                            ->where(function ($endpoints): void {
                                $endpoints->whereNotNull('from_location_id')->orWhereNull('to_location_id');
                            });
                    })
                    ->orWhere(function ($shape): void {
                        $shape->whereIn('movement_type', ['ISSUE', 'DISPOSE'])
                            ->where(function ($endpoints): void {
                                $endpoints->whereNull('from_location_id')->orWhereNotNull('to_location_id');
                            });
                    })
                    ->orWhere(function ($shape): void {
                        $shape->where('movement_type', 'TRANSFER')
                            ->where(function ($endpoints): void {
                                $endpoints->whereNull('from_location_id')
                                    ->orWhereNull('to_location_id')
                                    ->orWhereColumn('from_location_id', 'to_location_id');
                            });
                    })
                    ->orWhere(function ($shape): void {
                        $shape->where('movement_type', 'ADJUST')
                            ->where(function ($endpoints): void {
                                $endpoints->whereNull('from_location_id')->whereNull('to_location_id')
                                    ->orWhereNotNull('from_location_id')->whereNotNull('to_location_id');
                            });
                    });
            })
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'movement_type', 'qty', 'from_location_id', 'to_location_id']);

        if ($duplicateNullTuples->isEmpty() && $invalidBalances->isEmpty() && $invalidMovements->isEmpty()) {
            return;
        }

        $details = [];
        if ($duplicateNullTuples->isNotEmpty()) {
            $details[] = 'duplicate null-lot tuples: '.$duplicateNullTuples->map(
                fn (object $row): string => "item={$row->item_id}, location={$row->location_id}, rows={$row->tuple_count}",
            )->implode('; ');
        }
        if ($invalidBalances->isNotEmpty()) {
            $details[] = 'invalid balances: '.$invalidBalances->map(
                fn (object $row): string => "#{$row->id} (item={$row->item_id}, lot=".($row->lot_id ?? 'null').", location={$row->location_id}, on_hand={$row->on_hand_qty}, reserved={$row->reserved_qty})",
            )->implode('; ');
        }
        if ($invalidMovements->isNotEmpty()) {
            $details[] = 'invalid movements: '.$invalidMovements->map(
                fn (object $row): string => "#{$row->id} ({$row->movement_type}, qty={$row->qty}, from=".($row->from_location_id ?? 'null').', to='.($row->to_location_id ?? 'null').')',
            )->implode('; ');
        }

        throw new RuntimeException('Inventory PostgreSQL integrity guards were not installed. Resolve these legacy records without rewriting history, then retry: '.implode(' | ', $details));
    }

    private function installPostgresTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_inventory_movement_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'inventory movements are append-only; use a compensating movement';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER inventory_movements_append_only
                BEFORE UPDATE OR DELETE ON inventory_movements
                FOR EACH ROW EXECUTE FUNCTION prevent_inventory_movement_mutation();

            CREATE TRIGGER inventory_movements_no_truncate
                BEFORE TRUNCATE ON inventory_movements
                FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_movement_mutation();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_inventory_return_insert() RETURNS trigger AS $$
            BEGIN
                IF NEW.movement_type = 'RETURN' THEN
                    RAISE EXCEPTION 'new RETURN movements are disabled until a return workflow is approved';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER inventory_movements_return_insert_block
                BEFORE INSERT ON inventory_movements
                FOR EACH ROW EXECUTE FUNCTION reject_inventory_return_insert();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_inventory_reserved_qty_write() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'INSERT' AND NEW.reserved_qty <> 0 THEN
                    RAISE EXCEPTION 'reserved_qty writes are disabled until an owning reservation workflow is approved';
                END IF;

                IF TG_OP = 'UPDATE' AND NEW.reserved_qty IS DISTINCT FROM OLD.reserved_qty THEN
                    RAISE EXCEPTION 'reserved_qty writes are disabled until an owning reservation workflow is approved';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER inventory_balances_reserved_qty_write_guard
                BEFORE INSERT OR UPDATE OF reserved_qty ON inventory_balances
            FOR EACH ROW EXECUTE FUNCTION prevent_inventory_reserved_qty_write();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_inventory_integrity_audit_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'inventory integrity audit evidence is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER inventory_integrity_audits_append_only
                BEFORE UPDATE OR DELETE ON inventory_integrity_audits
                FOR EACH ROW EXECUTE FUNCTION prevent_inventory_integrity_audit_mutation();
            CREATE TRIGGER inventory_integrity_audits_no_truncate
                BEFORE TRUNCATE ON inventory_integrity_audits
                FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_integrity_audit_mutation();

            CREATE TRIGGER inventory_integrity_audit_lines_append_only
                BEFORE UPDATE OR DELETE ON inventory_integrity_audit_lines
                FOR EACH ROW EXECUTE FUNCTION prevent_inventory_integrity_audit_mutation();
            CREATE TRIGGER inventory_integrity_audit_lines_no_truncate
                BEFORE TRUNCATE ON inventory_integrity_audit_lines
                FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_integrity_audit_mutation();
            SQL);
    }
};
