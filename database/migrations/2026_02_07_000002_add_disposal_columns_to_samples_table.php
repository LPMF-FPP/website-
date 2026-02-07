<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->enum('disposal_status', ['pending', 'eligible', 'disposed'])
                ->default('pending')
                ->after('status');
            $table->foreignId('disposal_id')
                ->nullable()
                ->after('disposal_status')
                ->constrained('sample_disposals')
                ->nullOnDelete();
            $table->timestamp('disposed_at')
                ->nullable()
                ->after('disposal_id');

            $table->index('disposal_status');
            $table->index('disposed_at');
        });
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropForeign(['disposal_id']);
            $table->dropIndex(['disposal_status']);
            $table->dropIndex(['disposed_at']);
            $table->dropColumn(['disposal_status', 'disposal_id', 'disposed_at']);
        });
    }
};
