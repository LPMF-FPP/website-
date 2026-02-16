<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $table = $connection->getTablePrefix().'users';

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_role_check");
            DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE {$table} ALTER COLUMN role TYPE VARCHAR(100)");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN role SET DEFAULT 'investigator'");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `role` VARCHAR(100) NOT NULL DEFAULT 'investigator'"
            );
        }
    }

    public function down(): void
    {
        $roles = [
            'investigator',
            'analyst',
            'lab_analyst',
            'petugas_lab',
            'admin',
            'supervisor',
            'analis',
            'penyelia',
            'manajer_teknis',
        ];

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $table = $connection->getTablePrefix().'users';

        if ($driver === 'pgsql') {
            $allowed = implode(', ', array_map(
                fn (string $role) => "'".str_replace("'", "''", $role)."'",
                $roles
            ));

            DB::statement("UPDATE {$table} SET role = 'investigator' WHERE role NOT IN ({$allowed})");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_role_check");
            DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_role_check CHECK (role IN ({$allowed}))");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN role SET DEFAULT 'investigator'");

            return;
        }

        if ($driver === 'mysql') {
            $allowed = implode("','", array_map(
                fn (string $role) => str_replace("'", "''", $role),
                $roles
            ));

            DB::statement(
                "UPDATE `{$table}` SET `role` = 'investigator' WHERE `role` NOT IN ('{$allowed}')"
            );
            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `role` "
                ."ENUM('{$allowed}') NOT NULL DEFAULT 'investigator'"
            );
        }
    }
};
