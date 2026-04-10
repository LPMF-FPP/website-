<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->string('approver_name')->nullable()->after('executed_by_role');
            $table->string('approver_role')->nullable()->after('approver_name');
        });
    }

    public function down(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->dropColumn(['approver_name', 'approver_role']);
        });
    }
};
