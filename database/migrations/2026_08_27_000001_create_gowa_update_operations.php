<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gowa_update_scopes', function (Blueprint $table): void {
            $table->string('scope', 32)->primary();
            $table->unsignedBigInteger('current_fence')->default(0);
            $table->uuid('active_operation_id')->nullable();
            $table->string('intervention_generation', 128)->default('initial');
            $table->timestamps();
        });

        Schema::create('gowa_update_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope', 32)->default('gowa')->index();
            $table->string('release_id', 128);
            $table->string('requested_version', 128);
            $table->string('requested_digest', 255);
            $table->string('previous_version', 128)->nullable();
            $table->string('previous_digest', 255)->nullable();
            $table->string('status', 32);
            $table->string('idempotency_key', 191)->unique();
            $table->uuid('client_action_uuid');
            $table->unsignedBigInteger('fencing_token')->default(0);
            $table->string('checkpoint', 64)->nullable();
            $table->string('root_authority_generation', 128)->nullable();
            $table->timestampTz('heartbeat_at')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->uuid('retry_of_id')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message_key', 128)->nullable();
            $table->json('preservation_snapshot')->nullable();
            $table->json('feature_snapshot')->nullable();
            $table->timestampsTz();
            $table->foreign('scope')->references('scope')->on('gowa_update_scopes');
            $table->index(['scope', 'status']);
            $table->unique(['requested_by', 'client_action_uuid']);
        });

        Schema::table('gowa_update_operations', function (Blueprint $table): void {
            $table->foreign('retry_of_id')->references('id')->on('gowa_update_operations')->nullOnDelete();
        });
        Schema::table('gowa_update_scopes', function (Blueprint $table): void {
            $table->foreign('active_operation_id')->references('id')->on('gowa_update_operations')->nullOnDelete();
        });

        Schema::create('gowa_update_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id');
            $table->uuid('runner_event_id')->unique();
            $table->unsignedBigInteger('fencing_token');
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32)->nullable();
            $table->string('code', 64);
            $table->json('safe_meta')->nullable();
            $table->timestampTz('occurred_at');
            $table->foreign('operation_id')->references('id')->on('gowa_update_operations')->cascadeOnDelete();
            $table->index(['operation_id', 'occurred_at']);
        });

        Schema::create('gowa_update_attestations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id');
            $table->unsignedBigInteger('fencing_token');
            $table->string('plane', 32);
            $table->string('policy_version', 64);
            $table->string('snapshot_hash', 128);
            $table->string('container_identity', 255);
            $table->boolean('passed');
            $table->timestampTz('observed_at');
            $table->foreign('operation_id')->references('id')->on('gowa_update_operations')->cascadeOnDelete();
            $table->unique(['operation_id', 'fencing_token', 'plane']);
        });

        DB::table('gowa_update_scopes')->insert(['scope' => 'gowa', 'current_fence' => 0, 'intervention_generation' => 'initial', 'created_at' => now(), 'updated_at' => now()]);
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX gowa_update_operations_one_active ON gowa_update_operations (scope) WHERE status IN ('queued','preparing','updating','verifying','reconciling')");
            DB::statement("ALTER TABLE gowa_update_operations ADD CONSTRAINT gowa_update_operations_status_check CHECK (status IN ('queued','preparing','updating','verifying','reconciling','succeeded','failed','rolled_back','degraded'))");
            DB::statement("ALTER TABLE gowa_update_operations ADD CONSTRAINT gowa_update_operations_scope_check CHECK (scope = 'gowa')");
            DB::statement('ALTER TABLE gowa_update_operations ADD CONSTRAINT gowa_update_operations_uuid_check CHECK (client_action_uuid IS NOT NULL)');
            DB::statement("ALTER TABLE gowa_update_operations ADD CONSTRAINT gowa_update_operations_digest_check CHECK (requested_digest ~ '^sha256:[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gowa_update_attestations');
        Schema::dropIfExists('gowa_update_events');
        Schema::table('gowa_update_operations', function (Blueprint $table): void {
            $table->dropForeign(['scope']);
        });
        Schema::table('gowa_update_scopes', function (Blueprint $table): void {
            $table->dropForeign(['active_operation_id']);
        });
        Schema::dropIfExists('gowa_update_scopes');
        Schema::dropIfExists('gowa_update_operations');
    }
};
