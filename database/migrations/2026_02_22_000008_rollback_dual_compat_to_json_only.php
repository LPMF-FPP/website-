<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('qmh_audits', 'migration_phase')) {
            DB::table('qmh_audits')->update([
                'migration_phase' => 'dual',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('qmh_audits', 'migration_phase')) {
            DB::table('qmh_audits')->update([
                'migration_phase' => 'pivot_only',
                'updated_at' => now(),
            ]);
        }
    }
};
