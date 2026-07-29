<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLES = [
        'investigator',
        'analis',
        'penyelia',
        'manajer_teknis',
        'admin',
        'supervisor',
        'analyst',
        'lab_analyst',
        'petugas_lab',
    ];

    private const PERMISSIONS = [
        ['name' => 'guest-book.view', 'display_name' => 'Lihat Buku Tamu', 'module' => 'guest-book', 'action' => 'view'],
        ['name' => 'guest-book.create', 'display_name' => 'Tambah Buku Tamu', 'module' => 'guest-book', 'action' => 'create'],
        ['name' => 'guest-book.edit', 'display_name' => 'Edit Buku Tamu', 'module' => 'guest-book', 'action' => 'edit'],
        ['name' => 'guest-book.checkout', 'display_name' => 'Catat Keluar Buku Tamu', 'module' => 'guest-book', 'action' => 'checkout'],
        ['name' => 'guest-book.delete', 'display_name' => 'Hapus Buku Tamu', 'module' => 'guest-book', 'action' => 'delete'],
        ['name' => 'guest-book.export', 'display_name' => 'Export Buku Tamu', 'module' => 'guest-book', 'action' => 'export'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $timestamp = now();
            $permissions = collect(self::PERMISSIONS)
                ->map(fn (array $permission): array => [
                    ...$permission,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->all();

            DB::table('permissions')->upsert(
                $permissions,
                ['name'],
                ['display_name', 'module', 'action', 'updated_at']
            );

            $permissionIds = DB::table('permissions')
                ->whereIn('name', collect(self::PERMISSIONS)->pluck('name'))
                ->pluck('id');

            $roles = collect(self::ROLES)
                ->merge(DB::table('users')->whereNotNull('role')->distinct()->pluck('role'))
                ->merge(DB::table('role_permissions')->whereNotNull('role')->distinct()->pluck('role'))
                ->filter(fn ($role): bool => is_string($role) && trim($role) !== '')
                ->unique()
                ->values();

            $rows = $roles->crossJoin($permissionIds)
                ->map(fn (array $rolePermission): array => [
                    'role' => $rolePermission[0],
                    'permission_id' => $rolePermission[1],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->all();

            if ($rows !== []) {
                DB::table('role_permissions')->insertOrIgnore($rows);
            }
        });

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    Cache::forget('user_permissions_'.$user->id);
                }
            });
    }

    public function down(): void
    {
        // This data grant is intentionally irreversible to avoid removing pre-existing access.
    }
};
