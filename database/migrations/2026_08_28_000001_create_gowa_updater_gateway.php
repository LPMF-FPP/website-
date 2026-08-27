<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->postgresGatewayEnabled()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        DB::statement('CREATE SCHEMA IF NOT EXISTS updater_gateway');
        DB::statement('REVOKE ALL ON SCHEMA updater_gateway FROM PUBLIC');

        Schema::create('updater_gateway.dispatch_claims', function (Blueprint $table): void {
            $table->uuid('operation_id')->primary();
            $table->string('scope', 32);
            $table->string('release_id', 128);
            $table->unsignedBigInteger('fencing_token');
            $table->uuid('claim_nonce')->unique();
            $table->string('owner', 64);
            $table->string('catalog_generation', 128);
            $table->string('revocation_generation', 128);
            $table->json('claim_payload');
            $table->char('payload_hash', 64);
            $table->timestampTz('claimed_at');
            $table->timestampTz('lease_expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE updater_gateway.dispatch_claims ADD CONSTRAINT gowa_updater_dispatch_scope_check CHECK (scope = 'gowa')");
        DB::statement("ALTER TABLE updater_gateway.dispatch_claims ADD CONSTRAINT gowa_updater_dispatch_hash_check CHECK (payload_hash ~ '^[0-9a-f]{64}$')");
        DB::statement('REVOKE ALL ON TABLE updater_gateway.dispatch_claims FROM PUBLIC');
    }

    public function down(): void
    {
        if (! $this->postgresGatewayEnabled()) {
            return;
        }

        DB::statement('DROP SCHEMA IF EXISTS updater_gateway CASCADE');
    }

    private function postgresGatewayEnabled(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql'
            && (! app()->environment('testing') || (bool) env('GOWA_UPDATER_RUN_PG_INTEGRATION', false));
    }
};
