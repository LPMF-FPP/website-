<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->foreignId('witness_user_id')
                ->nullable()
                ->after('witness_role')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('witness_user_id');
        });
    }
};
