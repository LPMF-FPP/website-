<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gowa_update_dispatch_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
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
            $table->timestampTz('consumed_at');
            $table->timestampsTz();
            $table->foreign('operation_id')->references('id')->on('gowa_update_operations')->cascadeOnDelete();
            $table->foreign('scope')->references('scope')->on('gowa_update_scopes');
            $table->index(['scope', 'fencing_token']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE gowa_update_dispatch_claims ADD CONSTRAINT gowa_update_dispatch_claims_scope_check CHECK (scope = 'gowa')");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE gowa_update_dispatch_claims ADD CONSTRAINT gowa_update_dispatch_claims_digest_payload_check CHECK ((claim_payload->>'digest') ~ '^sha256:[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gowa_update_dispatch_claims');
    }
};
