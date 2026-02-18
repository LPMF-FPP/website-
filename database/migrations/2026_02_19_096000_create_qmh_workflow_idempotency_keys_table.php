<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_workflow_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 100);
            $table->string('idempotency_key', 200);
            $table->string('request_hash', 64)->nullable();
            $table->string('result_ref')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key'], 'qmh_workflow_idempotency_scope_key_unique');
            $table->index('expires_at', 'qmh_workflow_idempotency_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_workflow_idempotency_keys');
    }
};
