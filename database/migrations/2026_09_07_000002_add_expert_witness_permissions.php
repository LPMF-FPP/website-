<?php

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'sahli.view', 'display_name' => 'Lihat Saksi Ahli', 'module' => 'sahli', 'action' => 'view'],
            ['name' => 'sahli.create', 'display_name' => 'Tambah Saksi Ahli', 'module' => 'sahli', 'action' => 'create'],
            ['name' => 'sahli.edit', 'display_name' => 'Edit Saksi Ahli', 'module' => 'sahli', 'action' => 'edit'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        foreach (['admin', 'supervisor', 'manajer_teknis', 'penyelia', 'analis', 'analyst', 'lab_analyst'] as $role) {
            foreach ($permissions as $permission) {
                $record = Permission::where('name', $permission['name'])->first();
                if ($record) {
                    RolePermission::updateOrCreate(['role' => $role, 'permission_id' => $record->id]);
                }
            }
        }
    }

    public function down(): void
    {
        // Permissions are shared authorization data and cannot be safely removed on rollback.
    }
};
