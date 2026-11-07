<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gowa_update_attestation_renewals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id');
            $table->unsignedBigInteger('fencing_token');
            $table->string('plane', 32);
            $table->string('policy_version', 64);
            $table->string('snapshot_hash', 128);
            $table->string('container_identity', 255);
            $table->boolean('passed');
            $table->timestampTz('observed_at');
            $table->foreign('operation_id')->references('id')->on('gowa_update_operations')->restrictOnDelete();
            $table->unique(['operation_id', 'fencing_token', 'plane', 'observed_at'], 'gowa_attestation_renewal_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE gowa_update_attestation_renewals ADD CONSTRAINT gowa_attestation_renewal_plane_check CHECK (plane IN ('root','runtime'))");
            DB::statement("ALTER TABLE gowa_update_attestation_renewals ADD CONSTRAINT gowa_attestation_renewal_hash_check CHECK (snapshot_hash ~ '^[a-f0-9]{64}$')");
            DB::statement('ALTER TABLE gowa_update_attestation_renewals ADD CONSTRAINT gowa_attestation_renewal_fence_check CHECK (fencing_token > 0)');
        }
    }

    public function down(): void
    {
        throw new \LogicException('Attestation renewal history must not be deleted by rollback.');
    }
};
