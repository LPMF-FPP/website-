<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_request_id')->constrained('test_requests')->cascadeOnDelete();
            $table->foreignId('sample_id')->constrained('samples')->cascadeOnDelete();
            $table->string('method_code');
            $table->foreignId('instrument_asset_id')->constrained('instrument_assets')->cascadeOnDelete();
            $table->enum('usage_type', ['PREP', 'RUN'])->default('RUN');
            $table->timestamp('logged_at');
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('logged_at');
            $table->index(['sample_id', 'method_code']);
            $table->index(['instrument_asset_id', 'logged_at']);
            $table->index('test_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_usage_logs');
    }
};
