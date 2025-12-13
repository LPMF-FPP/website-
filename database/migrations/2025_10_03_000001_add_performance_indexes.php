<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Test Requests indexes
        Schema::table('test_requests', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
            $table->index('completed_at');
            $table->index(['status', 'created_at']); // Composite for filtered sorting
        });

        // Samples indexes
        Schema::table('samples', function (Blueprint $table) {
            $table->index('status');
            $table->index(['test_request_id', 'created_at']); // Composite for efficient listing
        });

        // Sample Test Processes indexes
        Schema::table('sample_test_processes', function (Blueprint $table) {
            $table->index('stage');
            $table->index('completed_at');
            $table->index(['sample_id', 'stage', 'completed_at']); // Composite for delivery filter
        });
    }

    public function down(): void
    {
        // Test Requests
        Schema::table('test_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        // Samples
        Schema::table('samples', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['test_request_id', 'created_at']);
        });

        // Sample Test Processes
        Schema::table('sample_test_processes', function (Blueprint $table) {
            $table->dropIndex(['stage']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['sample_id', 'stage', 'completed_at']);
        });
    }
};
