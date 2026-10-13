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
        Schema::create('inventory_item_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('previous_is_active');
            $table->boolean('is_active');
            $table->string('event_type', 16);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['inventory_item_id', 'occurred_at'], 'inventory_item_lifecycle_events_history_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_item_lifecycle_event_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory item lifecycle events are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_item_lifecycle_events_append_only
                    BEFORE UPDATE OR DELETE ON inventory_item_lifecycle_events
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_item_lifecycle_event_mutation();
                CREATE TRIGGER inventory_item_lifecycle_events_no_truncate
                    BEFORE TRUNCATE ON inventory_item_lifecycle_events
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_item_lifecycle_event_mutation();
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_item_history_delete() RETURNS trigger AS $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM inventory_balances WHERE item_id = OLD.id)
                       OR EXISTS (SELECT 1 FROM inventory_lots WHERE item_id = OLD.id)
                       OR EXISTS (SELECT 1 FROM inventory_movements WHERE item_id = OLD.id)
                       OR EXISTS (SELECT 1 FROM inventory_item_identity_events WHERE inventory_item_id = OLD.id)
                       OR EXISTS (SELECT 1 FROM inventory_item_lifecycle_events WHERE inventory_item_id = OLD.id) THEN
                        RAISE EXCEPTION 'inventory items with history cannot be deleted'
                            USING ERRCODE = '23503';
                    END IF;

                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_items_preserve_history_before_delete
                    BEFORE DELETE ON inventory_items
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_item_history_delete();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_items_preserve_history_before_delete ON inventory_items');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_item_history_delete()');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_item_lifecycle_events_append_only ON inventory_item_lifecycle_events');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_item_lifecycle_events_no_truncate ON inventory_item_lifecycle_events');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_item_lifecycle_event_mutation()');
        }

        Schema::dropIfExists('inventory_item_lifecycle_events');
    }
};
