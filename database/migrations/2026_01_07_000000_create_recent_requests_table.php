<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recent_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_request_id')->constrained('test_requests')->cascadeOnDelete();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'test_request_id']);
            $table->index(['user_id', 'last_opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recent_requests');
    }
};
