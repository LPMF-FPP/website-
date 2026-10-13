<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lot_tracking_policies', function (Blueprint $table): void {
            $table->string('item_type', 32)->primary();
            $table->string('lot_tracking_mode', 16)->nullable();
            $table->string('evidence_reference', 255)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        foreach (array_keys(InventoryItem::ITEM_TYPES) as $itemType) {
            DB::table('inventory_lot_tracking_policies')->insert([
                'item_type' => $itemType,
                'lot_tracking_mode' => null,
                'evidence_reference' => null,
                'decided_by_user_id' => null,
                'decided_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('inventory_lot_tracking_policy_events', function (Blueprint $table): void {
            $table->id();
            $table->string('item_type', 32);
            $table->foreign('item_type', 'inventory_lot_policy_events_item_type_fk')
                ->references('item_type')
                ->on('inventory_lot_tracking_policies')
                ->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_lot_tracking_mode', 16)->nullable();
            $table->string('lot_tracking_mode', 16)->nullable();
            $table->string('evidence_reference', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['item_type', 'occurred_at'], 'inventory_lot_policy_events_history_index');
        });

        Schema::create('inventory_opening_baselines', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 16);
            $table->string('source_fingerprint', 64);
            $table->json('policy_snapshot');
            $table->json('blockers')->nullable();
            $table->unsignedInteger('tuple_count')->default(0);
            $table->unsignedInteger('counted_count')->default(0);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('stale_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'generated_at'], 'inventory_opening_baselines_status_history_index');
            $table->index(['generated_by', 'generated_at'], 'inventory_opening_baselines_counter_history_index');
        });

        Schema::create('inventory_mutation_mode_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_mode', 32);
            $table->string('new_mode', 32);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['occurred_at', 'id'], 'inventory_mutation_mode_events_history_index');
        });

        Schema::create('inventory_opening_baseline_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('baseline_id')->constrained('inventory_opening_baselines')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->string('item_code_snapshot', 64)->nullable();
            $table->string('item_name_snapshot', 255);
            $table->string('item_type_snapshot', 32);
            $table->string('uom_snapshot', 32);
            $table->string('lot_no_snapshot', 100)->nullable();
            $table->string('location_name_snapshot', 255);
            $table->string('location_type_snapshot', 32);
            $table->decimal('system_on_hand_qty', 14, 3)->default(0);
            $table->decimal('ledger_on_hand_qty', 14, 3)->default(0);
            $table->decimal('reserved_qty_snapshot', 14, 3)->default(0);
            $table->boolean('balance_existed')->default(false);
            $table->decimal('physical_qty', 14, 3)->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            $table->index(['baseline_id', 'location_id', 'item_id'], 'inventory_opening_baseline_lines_scope_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_lot_tracking_policies ADD CONSTRAINT inventory_lot_tracking_policy_type_check CHECK (item_type IN ('REAGENT', 'CONSUMABLE', 'STANDARD', 'CONTROL', 'OTHER'))");
            DB::statement("ALTER TABLE inventory_lot_tracking_policies ADD CONSTRAINT inventory_lot_tracking_policy_mode_check CHECK (lot_tracking_mode IS NULL OR lot_tracking_mode IN ('lot_tracked', 'untracked'))");
            DB::statement("ALTER TABLE inventory_lot_tracking_policies ADD CONSTRAINT inventory_lot_tracking_policy_approval_check CHECK ((lot_tracking_mode IS NULL AND decided_by_user_id IS NULL AND decided_at IS NULL) OR (lot_tracking_mode IS NOT NULL AND evidence_reference IS NOT NULL AND BTRIM(evidence_reference) <> '' AND decided_by_user_id IS NOT NULL AND decided_at IS NOT NULL))");
            DB::statement("ALTER TABLE inventory_lot_tracking_policy_events ADD CONSTRAINT inventory_lot_tracking_policy_event_modes_check CHECK ((previous_lot_tracking_mode IS NULL OR previous_lot_tracking_mode IN ('lot_tracked', 'untracked')) AND (lot_tracking_mode IS NULL OR lot_tracking_mode IN ('lot_tracked', 'untracked')))");
            DB::statement("ALTER TABLE inventory_opening_baselines ADD CONSTRAINT inventory_opening_baselines_status_check CHECK (status IN ('blocked', 'stale', 'draft'))");
            DB::statement("ALTER TABLE inventory_mutation_mode_events ADD CONSTRAINT inventory_mutation_mode_events_mode_check CHECK (previous_mode IN ('disabled', 'opening_baseline', 'active') AND new_mode IN ('disabled', 'opening_baseline', 'active'))");
            DB::statement('ALTER TABLE inventory_opening_baseline_lines ADD CONSTRAINT inventory_opening_baseline_lines_qty_check CHECK (system_on_hand_qty >= 0 AND reserved_qty_snapshot >= 0 AND (physical_qty IS NULL OR physical_qty >= 0))');
            DB::statement('ALTER TABLE inventory_opening_baseline_lines ADD CONSTRAINT inventory_opening_baseline_lines_count_actor_check CHECK ((physical_qty IS NULL AND counted_by IS NULL AND counted_at IS NULL) OR (physical_qty IS NOT NULL AND counted_by IS NOT NULL AND counted_at IS NOT NULL))');
            DB::statement('CREATE UNIQUE INDEX inventory_opening_baseline_lines_null_lot_unique ON inventory_opening_baseline_lines (baseline_id, item_id, location_id) WHERE lot_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX inventory_opening_baseline_lines_lot_unique ON inventory_opening_baseline_lines (baseline_id, item_id, lot_id, location_id) WHERE lot_id IS NOT NULL');

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_lot_tracking_policy_event_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory lot tracking policy events are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_lot_tracking_policy_events_append_only
                    BEFORE UPDATE OR DELETE ON inventory_lot_tracking_policy_events
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_lot_tracking_policy_event_mutation();
                CREATE TRIGGER inventory_lot_tracking_policy_events_no_truncate
                    BEFORE TRUNCATE ON inventory_lot_tracking_policy_events
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_lot_tracking_policy_event_mutation();
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_mutation_mode_event_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory mutation mode events are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_mutation_mode_events_append_only
                    BEFORE UPDATE OR DELETE ON inventory_mutation_mode_events
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_mutation_mode_event_mutation();
                CREATE TRIGGER inventory_mutation_mode_events_no_truncate
                    BEFORE TRUNCATE ON inventory_mutation_mode_events
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_mutation_mode_event_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_lot_tracking_policy_events_append_only ON inventory_lot_tracking_policy_events');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_lot_tracking_policy_events_no_truncate ON inventory_lot_tracking_policy_events');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_lot_tracking_policy_event_mutation()');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_mutation_mode_events_append_only ON inventory_mutation_mode_events');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_mutation_mode_events_no_truncate ON inventory_mutation_mode_events');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_mutation_mode_event_mutation()');
            DB::statement('DROP INDEX IF EXISTS inventory_opening_baseline_lines_null_lot_unique');
            DB::statement('DROP INDEX IF EXISTS inventory_opening_baseline_lines_lot_unique');
            DB::statement('ALTER TABLE inventory_opening_baseline_lines DROP CONSTRAINT IF EXISTS inventory_opening_baseline_lines_qty_check');
            DB::statement('ALTER TABLE inventory_opening_baseline_lines DROP CONSTRAINT IF EXISTS inventory_opening_baseline_lines_count_actor_check');
            DB::statement('ALTER TABLE inventory_opening_baselines DROP CONSTRAINT IF EXISTS inventory_opening_baselines_status_check');
            DB::statement('ALTER TABLE inventory_mutation_mode_events DROP CONSTRAINT IF EXISTS inventory_mutation_mode_events_mode_check');
            DB::statement('ALTER TABLE inventory_lot_tracking_policy_events DROP CONSTRAINT IF EXISTS inventory_lot_tracking_policy_event_modes_check');
            DB::statement('ALTER TABLE inventory_lot_tracking_policies DROP CONSTRAINT IF EXISTS inventory_lot_tracking_policy_mode_check');
            DB::statement('ALTER TABLE inventory_lot_tracking_policies DROP CONSTRAINT IF EXISTS inventory_lot_tracking_policy_approval_check');
            DB::statement('ALTER TABLE inventory_lot_tracking_policies DROP CONSTRAINT IF EXISTS inventory_lot_tracking_policy_type_check');
        }

        Schema::dropIfExists('inventory_opening_baseline_lines');
        Schema::dropIfExists('inventory_opening_baselines');
        Schema::dropIfExists('inventory_mutation_mode_events');
        Schema::dropIfExists('inventory_lot_tracking_policy_events');
        Schema::dropIfExists('inventory_lot_tracking_policies');
    }
};
