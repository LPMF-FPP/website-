<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'gowa-update.status',
        'gowa-update.detail',
        'gowa-update.request',
        'gowa-update.retry',
        'gowa-update.audit',
    ];

    public function up(): void
    {
        $now = now();
        $rows = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id')
            ->map(fn (int $permissionId): array => [
                'role' => 'admin',
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('role_permissions')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Permission synchronization is intentionally append-only in production.
    }
};
