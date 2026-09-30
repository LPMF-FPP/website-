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
        Schema::create('gowa_update_preparations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->uuid('action_uuid');
            $table->string('idempotency_key', 191)->unique();
            $table->string('status', 24)->index();
            $table->string('requested_version', 128);
            $table->string('release_id', 128)->nullable();
            $table->string('digest', 71)->nullable();
            $table->string('catalog_generation', 128)->nullable();
            $table->string('runtime_digest', 71);
            $table->string('container_identity', 255);
            $table->string('failure_code', 64)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('prepared_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
            $table->index(['requested_by', 'status', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX gowa_update_preparations_one_active ON gowa_update_preparations ((1)) WHERE status IN ('queued','preparing')");
            DB::statement("ALTER TABLE gowa_update_preparations ADD CONSTRAINT gowa_update_preparations_status_check CHECK (status IN ('queued','preparing','ready','failed','consumed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gowa_update_preparations');
    }
};
