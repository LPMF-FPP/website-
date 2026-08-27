<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE gowa_update_events ADD CONSTRAINT gowa_update_events_fence_check CHECK (fencing_token > 0)');
        DB::statement("ALTER TABLE gowa_update_attestations ADD CONSTRAINT gowa_update_attestations_plane_check CHECK (plane IN ('root','runtime'))");
        DB::statement("ALTER TABLE gowa_update_attestations ADD CONSTRAINT gowa_update_attestations_hash_check CHECK (snapshot_hash ~ '^[a-f0-9]{64}$')");
        DB::statement('ALTER TABLE gowa_update_attestations ADD CONSTRAINT gowa_update_attestations_policy_check CHECK (length(policy_version) BETWEEN 1 AND 64)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE gowa_update_events DROP CONSTRAINT IF EXISTS gowa_update_events_fence_check');
        DB::statement('ALTER TABLE gowa_update_attestations DROP CONSTRAINT IF EXISTS gowa_update_attestations_plane_check');
        DB::statement('ALTER TABLE gowa_update_attestations DROP CONSTRAINT IF EXISTS gowa_update_attestations_hash_check');
        DB::statement('ALTER TABLE gowa_update_attestations DROP CONSTRAINT IF EXISTS gowa_update_attestations_policy_check');
    }
};
