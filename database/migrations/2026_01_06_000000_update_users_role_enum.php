<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateRoleEnum([
            'investigator',
            'analyst',
            'lab_analyst',
            'petugas_lab',
            'admin',
            'supervisor',
        ]);
    }

    public function down(): void
    {
        $this->updateRoleEnum([
            'investigator',
            'analyst',
            'admin',
            'supervisor',
        ]);
    }

    private function updateRoleEnum(array $roles): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $table = $connection->getTablePrefix().'users';
        $column = 'role';
        $default = 'investigator';

        if ($driver === 'pgsql') {
            $constraint = $table.'_'.$column.'_check';
            $allowed = implode(', ', array_map(
                fn (string $role) => "'".str_replace("'", "''", $role)."'",
                $roles
            ));

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$column} IN ({$allowed}))");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT '{$default}'");

            return;
        }

        if ($driver === 'mysql') {
            $allowed = implode("','", array_map(
                fn (string $role) => str_replace("'", "''", $role),
                $roles
            ));
            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `{$column}` "
                ."ENUM('{$allowed}') NOT NULL DEFAULT '{$default}'"
            );

            return;
        }
    }
};
