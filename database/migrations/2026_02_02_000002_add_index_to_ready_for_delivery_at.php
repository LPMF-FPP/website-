<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            $table->index('ready_for_delivery_at');
        });
    }

    public function down(): void
    {
        Schema::table('test_requests', function (Blueprint $table) {
            $table->dropIndex(['ready_for_delivery_at']);
        });
    }
};
