<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->json('witness_entries')
                ->nullable()
                ->after('witness_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->dropColumn('witness_entries');
        });
    }
};
