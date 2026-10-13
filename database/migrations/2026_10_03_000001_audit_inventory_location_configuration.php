<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOCATION_TYPE_CONSTRAINT = 'inventory_locations_location_type_check';

    public function up(): void
    {
        Schema::create('inventory_location_configuration_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 16);
            $table->json('previous_configuration')->nullable();
            $table->json('new_configuration');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['location_id', 'occurred_at'], 'inventory_location_config_history_index');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_location_configuration_events
            ADD CONSTRAINT inventory_location_configuration_event_type_check
            CHECK (
                (event_type = 'created' AND previous_configuration IS NULL)
                OR (event_type = 'updated' AND previous_configuration IS NOT NULL)
            )
            SQL);
        DB::statement('LOCK TABLE inventory_locations IN SHARE MODE');
        $invalidLocations = DB::table('inventory_locations')
            ->whereNotIn('location_type', ['warehouse', 'lab', 'cold_storage'])
            ->orderBy('id')
            ->get(['id', 'location_type']);

        if ($invalidLocations->isNotEmpty()) {
            $details = $invalidLocations
                ->map(fn (object $location): string => '#'.$location->id.'='.json_encode($location->location_type, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ->implode(', ');
            throw new RuntimeException("Unknown inventory location types block the taxonomy constraint. Resolve these records before retrying: {$details}");
        }

        DB::statement('ALTER TABLE inventory_locations ADD CONSTRAINT '.self::LOCATION_TYPE_CONSTRAINT." CHECK (location_type IN ('warehouse', 'lab', 'cold_storage'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_inventory_location_configuration_event_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'inventory location configuration events are append-only';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER inventory_location_configuration_events_append_only
                BEFORE UPDATE OR DELETE ON inventory_location_configuration_events
                FOR EACH ROW EXECUTE FUNCTION prevent_inventory_location_configuration_event_mutation();
            CREATE TRIGGER inventory_location_configuration_events_no_truncate
                BEFORE TRUNCATE ON inventory_location_configuration_events
                FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_location_configuration_event_mutation();
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_inventory_location_restriction_change() RETURNS trigger AS $$
            BEGIN
                IF NEW.is_restricted IS DISTINCT FROM OLD.is_restricted THEN
                    RAISE EXCEPTION 'inventory location restriction is immutable after creation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER inventory_locations_restriction_immutable
                BEFORE UPDATE OF is_restricted ON inventory_locations
                FOR EACH ROW EXECUTE FUNCTION prevent_inventory_location_restriction_change();
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_location_configuration_events_append_only ON inventory_location_configuration_events');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_location_configuration_events_no_truncate ON inventory_location_configuration_events');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_location_configuration_event_mutation()');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_locations_restriction_immutable ON inventory_locations');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_location_restriction_change()');
            DB::statement('ALTER TABLE inventory_location_configuration_events DROP CONSTRAINT IF EXISTS inventory_location_configuration_event_type_check');
            DB::statement('ALTER TABLE inventory_locations DROP CONSTRAINT IF EXISTS '.self::LOCATION_TYPE_CONSTRAINT);
        }

        Schema::dropIfExists('inventory_location_configuration_events');
    }
};
