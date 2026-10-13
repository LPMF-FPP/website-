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
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->string('item_code', 64)->nullable();
            $table->unique('item_code', 'inventory_items_item_code_unique');
        });

        Schema::create('inventory_item_identity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_item_code', 64)->nullable();
            $table->string('new_item_code', 64);
            $table->string('event_type', 16);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['inventory_item_id', 'occurred_at'], 'inventory_item_identity_events_history_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE inventory_items
                ADD CONSTRAINT inventory_items_item_code_format_check
                CHECK (item_code IS NULL OR (item_code = UPPER(BTRIM(item_code, ' ')) AND item_code ~ '^[A-Z0-9._-]+$'))
                SQL);

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_item_identity_event_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory item identity events are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_item_identity_events_append_only
                    BEFORE UPDATE OR DELETE ON inventory_item_identity_events
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_item_identity_event_mutation();
                CREATE TRIGGER inventory_item_identity_events_no_truncate
                    BEFORE TRUNCATE ON inventory_item_identity_events
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_item_identity_event_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_item_identity_events_append_only ON inventory_item_identity_events');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_item_identity_events_no_truncate ON inventory_item_identity_events');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_item_identity_event_mutation()');
            DB::statement('ALTER TABLE inventory_items DROP CONSTRAINT IF EXISTS inventory_items_item_code_format_check');
        }

        Schema::dropIfExists('inventory_item_identity_events');
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropUnique('inventory_items_item_code_unique');
            $table->dropColumn('item_code');
        });
    }
};
