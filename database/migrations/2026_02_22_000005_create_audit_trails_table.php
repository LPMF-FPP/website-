<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('table_name');
            $table->string('record_id');
            $table->string('action', 32);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('changed_by', 64)->nullable();
            $table->string('source', 24)->default('web');
            $table->timestampTz('changed_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id', 128)->nullable();
            $table->string('request_id', 128)->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->index(['table_name', 'record_id']);
            $table->index(['source', 'changed_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
