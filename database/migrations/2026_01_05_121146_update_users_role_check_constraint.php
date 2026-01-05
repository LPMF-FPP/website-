<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan role baru untuk staff analis:
     * - analis (analyst dalam bahasa Indonesia)
     * - penyelia (supervisor dalam bahasa Indonesia)
     * - manajer_teknis (technical manager)
     */
    public function up(): void
    {
        // Drop existing constraint
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        // Create new constraint with additional roles
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN (
                'investigator',
                'analyst',
                'lab_analyst',
                'petugas_lab',
                'admin',
                'supervisor',
                'analis',
                'penyelia',
                'manajer_teknis'
            ))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop current constraint
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        // Restore original constraint
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN (
                'investigator',
                'analyst',
                'lab_analyst',
                'petugas_lab',
                'admin',
                'supervisor'
            ))
        ");
    }
};
