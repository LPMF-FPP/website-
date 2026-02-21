<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qmh_audits', function (Blueprint $table) {
            $table->string('migration_phase', 20)->default('pivot_only')->after('status');
            $table->index('migration_phase');
        });

        DB::table('qmh_audits')->update(['migration_phase' => 'pivot_only']);
    }

    public function down(): void
    {
        Schema::table('qmh_audits', function (Blueprint $table) {
            $table->dropIndex(['migration_phase']);
            $table->dropColumn('migration_phase');
        });
    }
};
