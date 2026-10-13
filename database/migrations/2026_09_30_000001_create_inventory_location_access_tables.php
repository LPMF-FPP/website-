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
        Schema::create('inventory_location_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_transact')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'location_id'], 'inventory_location_access_user_location_unique');
        });

        Schema::create('inventory_location_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->boolean('previous_can_view');
            $table->boolean('previous_can_transact');
            $table->boolean('can_view');
            $table->boolean('can_transact');
            $table->string('action', 16);
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['target_user_id', 'location_id', 'occurred_at'], 'inventory_location_access_events_history_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_location_access_event_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory location access events are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_location_access_events_append_only
                    BEFORE UPDATE OR DELETE ON inventory_location_access_events
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_location_access_event_mutation();
                CREATE TRIGGER inventory_location_access_events_no_truncate
                    BEFORE TRUNCATE ON inventory_location_access_events
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_location_access_event_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_location_access_events_append_only ON inventory_location_access_events');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_location_access_events_no_truncate ON inventory_location_access_events');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_location_access_event_mutation()');
        }

        Schema::dropIfExists('inventory_location_access_events');
        Schema::dropIfExists('inventory_location_access');
    }
};
