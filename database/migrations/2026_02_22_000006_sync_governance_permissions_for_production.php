<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array<string, string>>
     */
    private array $permissions = [
        [
            'name' => 'action-item:verify',
            'display_name' => 'Verifikasi Action Item Governance',
            'module' => 'qmh-governance',
            'action' => 'verify',
        ],
        [
            'name' => 'action-item:close',
            'display_name' => 'Tutup Action Item Governance',
            'module' => 'qmh-governance',
            'action' => 'close',
        ],
        [
            'name' => 'action-item:reopen',
            'display_name' => 'Buka Ulang Action Item Governance',
            'module' => 'qmh-governance',
            'action' => 'reopen',
        ],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ($this->permissions as $permission) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $permission['name']],
                    [
                        'display_name' => $permission['display_name'],
                        'module' => $permission['module'],
                        'action' => $permission['action'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('name', collect($this->permissions)->pluck('name')->all())
                ->pluck('id', 'name');

            foreach (['admin', 'supervisor', 'manajer_teknis'] as $role) {
                foreach ($this->permissions as $permission) {
                    DB::table('role_permissions')->updateOrInsert(
                        [
                            'role' => $role,
                            'permission_id' => $permissionIds[$permission['name']],
                        ],
                        [
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', collect($this->permissions)->pluck('name')->all())
                ->pluck('id')
                ->all();

            if (! empty($permissionIds)) {
                DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        });
    }
};
