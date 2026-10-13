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
        Schema::create('inventory_cycle_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 24);
            $table->boolean('is_partial')->default(true);
            $table->string('purpose', 255)->nullable();
            $table->unsignedInteger('revision_number')->default(0);
            $table->unsignedInteger('tuple_count')->default(0);
            $table->unsignedInteger('counted_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'inventory_cycle_counts_status_created_index');
            $table->index(['created_by', 'created_at'], 'inventory_cycle_counts_counter_created_index');
        });

        Schema::create('inventory_cycle_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_count_id')->constrained('inventory_cycle_counts')->restrictOnDelete();
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
            $table->string('source_tuple_fingerprint', 64);
            $table->decimal('physical_qty', 14, 3)->nullable();
            $table->text('variance_reason')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            $table->index(['cycle_count_id', 'location_id', 'item_id'], 'inventory_cycle_count_lines_scope_index');
        });

        Schema::create('inventory_cycle_count_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_count_id')->constrained('inventory_cycle_counts')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('revision_status', 24);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('snapshot_hash', 64);
            $table->json('lines_snapshot');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['cycle_count_id', 'revision_number'], 'inventory_cycle_count_revision_unique');
        });

        Schema::create('inventory_cycle_count_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('inventory_cycle_count_revisions')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->string('resulting_source_fingerprint', 64)->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique('revision_id', 'inventory_cycle_count_decision_revision_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_cycle_counts ADD CONSTRAINT inventory_cycle_counts_status_check CHECK (status IN ('draft', 'pending_approval', 'completed', 'approved'))");
            DB::statement('ALTER TABLE inventory_cycle_counts ADD CONSTRAINT inventory_cycle_counts_partial_check CHECK (is_partial = TRUE)');
            DB::statement('ALTER TABLE inventory_cycle_count_lines ADD CONSTRAINT inventory_cycle_count_lines_qty_check CHECK (system_on_hand_qty >= 0 AND ledger_on_hand_qty >= 0 AND reserved_qty_snapshot >= 0 AND (physical_qty IS NULL OR physical_qty >= 0))');
            DB::statement('ALTER TABLE inventory_cycle_count_lines ADD CONSTRAINT inventory_cycle_count_lines_count_actor_check CHECK ((physical_qty IS NULL AND counted_by IS NULL AND counted_at IS NULL) OR (physical_qty IS NOT NULL AND counted_by IS NOT NULL AND counted_at IS NOT NULL))');
            DB::statement("ALTER TABLE inventory_cycle_count_revisions ADD CONSTRAINT inventory_cycle_count_revision_status_check CHECK (revision_status IN ('completed', 'pending_approval'))");
            DB::statement("ALTER TABLE inventory_cycle_count_decisions ADD CONSTRAINT inventory_cycle_count_decision_check CHECK (decision IN ('completed', 'approved', 'rejected', 'recount'))");
            DB::statement("ALTER TABLE inventory_cycle_count_decisions ADD CONSTRAINT inventory_cycle_count_decision_fingerprint_check CHECK ((decision IN ('completed', 'approved') AND resulting_source_fingerprint IS NOT NULL) OR (decision IN ('rejected', 'recount') AND resulting_source_fingerprint IS NULL))");
            DB::unprepared(<<<'SQL'
                CREATE UNIQUE INDEX inventory_cycle_count_lines_null_lot_unique
                    ON inventory_cycle_count_lines (cycle_count_id, item_id, location_id) WHERE lot_id IS NULL;
                CREATE UNIQUE INDEX inventory_cycle_count_lines_lot_unique
                    ON inventory_cycle_count_lines (cycle_count_id, item_id, lot_id, location_id) WHERE lot_id IS NOT NULL;
                CREATE OR REPLACE FUNCTION prevent_inventory_cycle_count_evidence_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory cycle-count revisions and decisions are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_cycle_count_revisions_append_only
                    BEFORE UPDATE OR DELETE ON inventory_cycle_count_revisions
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_cycle_count_evidence_mutation();
                CREATE TRIGGER inventory_cycle_count_revisions_no_truncate
                    BEFORE TRUNCATE ON inventory_cycle_count_revisions
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_cycle_count_evidence_mutation();
                CREATE TRIGGER inventory_cycle_count_decisions_append_only
                    BEFORE UPDATE OR DELETE ON inventory_cycle_count_decisions
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_cycle_count_evidence_mutation();
                CREATE TRIGGER inventory_cycle_count_decisions_no_truncate
                    BEFORE TRUNCATE ON inventory_cycle_count_decisions
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_cycle_count_evidence_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_cycle_count_revisions_append_only ON inventory_cycle_count_revisions');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_cycle_count_revisions_no_truncate ON inventory_cycle_count_revisions');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_cycle_count_decisions_append_only ON inventory_cycle_count_decisions');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_cycle_count_decisions_no_truncate ON inventory_cycle_count_decisions');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_cycle_count_evidence_mutation()');
            DB::statement('DROP INDEX IF EXISTS inventory_cycle_count_lines_null_lot_unique');
            DB::statement('DROP INDEX IF EXISTS inventory_cycle_count_lines_lot_unique');
            DB::statement('ALTER TABLE inventory_cycle_count_decisions DROP CONSTRAINT IF EXISTS inventory_cycle_count_decision_check');
            DB::statement('ALTER TABLE inventory_cycle_count_decisions DROP CONSTRAINT IF EXISTS inventory_cycle_count_decision_fingerprint_check');
            DB::statement('ALTER TABLE inventory_cycle_count_revisions DROP CONSTRAINT IF EXISTS inventory_cycle_count_revision_status_check');
            DB::statement('ALTER TABLE inventory_cycle_count_lines DROP CONSTRAINT IF EXISTS inventory_cycle_count_lines_count_actor_check');
            DB::statement('ALTER TABLE inventory_cycle_count_lines DROP CONSTRAINT IF EXISTS inventory_cycle_count_lines_qty_check');
            DB::statement('ALTER TABLE inventory_cycle_counts DROP CONSTRAINT IF EXISTS inventory_cycle_counts_partial_check');
            DB::statement('ALTER TABLE inventory_cycle_counts DROP CONSTRAINT IF EXISTS inventory_cycle_counts_status_check');
        }

        Schema::dropIfExists('inventory_cycle_count_decisions');
        Schema::dropIfExists('inventory_cycle_count_revisions');
        Schema::dropIfExists('inventory_cycle_count_lines');
        Schema::dropIfExists('inventory_cycle_counts');
    }
};
