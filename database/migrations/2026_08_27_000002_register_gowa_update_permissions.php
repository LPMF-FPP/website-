<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'gowa-update.status', 'display_name' => 'Lihat Status Pembaruan GOWA', 'module' => 'gowa-update', 'action' => 'status'],
            ['name' => 'gowa-update.detail', 'display_name' => 'Lihat Detail Pembaruan GOWA', 'module' => 'gowa-update', 'action' => 'detail'],
            ['name' => 'gowa-update.request', 'display_name' => 'Minta Pembaruan GOWA', 'module' => 'gowa-update', 'action' => 'request'],
            ['name' => 'gowa-update.retry', 'display_name' => 'Ulangi Pembaruan GOWA', 'module' => 'gowa-update', 'action' => 'retry'],
            ['name' => 'gowa-update.audit', 'display_name' => 'Lihat Audit Pembaruan GOWA', 'module' => 'gowa-update', 'action' => 'audit'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['name' => $permission['name']], array_merge($permission, ['updated_at' => now(), 'created_at' => now()]));
        }

    }

    public function down(): void
    {
        DB::table('permissions')->where('module', 'gowa-update')->delete();
    }
};
