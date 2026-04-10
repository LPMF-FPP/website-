<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->string('executed_by_identity')->nullable()->after('executed_by_role');
            $table->string('approver_identity')->nullable()->after('approver_role');
        });
    }

    public function down(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->dropColumn(['executed_by_identity', 'approver_identity']);
        });
    }
};
