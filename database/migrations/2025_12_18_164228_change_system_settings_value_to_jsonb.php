<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tableName = Schema::hasTable('settings') ? 'settings' : 'system_settings';
        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN value TYPE jsonb USING value::jsonb");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tableName = Schema::hasTable('settings') ? 'settings' : 'system_settings';
        DB::statement("ALTER TABLE {$tableName} ALTER COLUMN value TYPE json USING value::json");
    }
};
