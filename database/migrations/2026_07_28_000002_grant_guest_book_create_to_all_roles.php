<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CORE_ROLES = [
        'investigator',
        'analis',
        'penyelia',
        'manajer_teknis',
        'admin',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $timestamp = now();

            DB::table('permissions')->insertOrIgnore([
                'name' => 'guest-book.create',
                'display_name' => 'Tambah Buku Tamu',
                'module' => 'guest-book',
                'action' => 'create',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('permissions')
                ->where('name', 'guest-book.create')
                ->update([
                    'display_name' => 'Tambah Buku Tamu',
                    'module' => 'guest-book',
                    'action' => 'create',
                    'updated_at' => $timestamp,
                ]);

            $permissionId = DB::table('permissions')
                ->where('name', 'guest-book.create')
                ->value('id');

            $roles = collect(self::CORE_ROLES)
                ->merge(DB::table('users')->whereNotNull('role')->pluck('role'))
                ->merge(DB::table('role_permissions')->whereNotNull('role')->pluck('role'))
                ->filter(fn ($role): bool => is_string($role) && trim($role) !== '')
                ->map(fn (string $role): string => trim($role))
                ->unique()
                ->values();

            $rows = $roles->map(fn (string $role): array => [
                'role' => $role,
                'permission_id' => $permissionId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all();

            if ($rows !== []) {
                DB::table('role_permissions')->insertOrIgnore($rows);
            }
        });

        DB::table('users')->orderBy('id')->pluck('id')->each(
            fn ($userId) => Cache::forget('user_permissions_'.$userId)
        );
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'guest-book.create')
            ->value('id');

        if ($permissionId) {
            DB::table('role_permissions')
                ->where('permission_id', $permissionId)
                ->delete();
        }

        DB::table('users')->orderBy('id')->pluck('id')->each(
            fn ($userId) => Cache::forget('user_permissions_'.$userId)
        );
    }
};
