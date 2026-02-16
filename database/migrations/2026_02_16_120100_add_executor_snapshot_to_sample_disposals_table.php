<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->string('executed_by_name')->nullable()->after('executed_by');
            $table->string('executed_by_role')->nullable()->after('executed_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->dropColumn(['executed_by_name', 'executed_by_role']);
        });
    }
};
