<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $old = DB::table('permissions')->where('name', 'qmh.force_unlock')->first();
        $new = DB::table('permissions')->where('name', 'qmh.unlock.force')->first();

        if ($old === null && $new === null) {
            return;
        }

        if ($old !== null && $new === null) {
            DB::table('permissions')
                ->where('id', $old->id)
                ->update([
                    'name' => 'qmh.unlock.force',
                    'display_name' => 'Paksa Buka Kunci Dokumen Quality Management Hub',
                    'module' => 'qmh',
                    'action' => 'unlock-force',
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($old !== null && $new !== null) {
            $now = now();

            $roleRows = DB::table('role_permissions')
                ->where('permission_id', $old->id)
                ->get(['role']);

            foreach ($roleRows as $row) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $row->role,
                    'permission_id' => $new->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $userRows = DB::table('user_permissions')
                ->where('permission_id', $old->id)
                ->get(['user_id', 'granted']);

            foreach ($userRows as $row) {
                DB::table('user_permissions')->insertOrIgnore([
                    'user_id' => $row->user_id,
                    'permission_id' => $new->id,
                    'granted' => (bool) $row->granted,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('role_permissions')->where('permission_id', $old->id)->delete();
            DB::table('user_permissions')->where('permission_id', $old->id)->delete();
            DB::table('permissions')->where('id', $old->id)->delete();
        }

        DB::table('permissions')
            ->where('name', 'qmh.unlock.force')
            ->update([
                'display_name' => 'Paksa Buka Kunci Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'unlock-force',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $old = DB::table('permissions')->where('name', 'qmh.force_unlock')->first();
        $new = DB::table('permissions')->where('name', 'qmh.unlock.force')->first();

        if ($old === null && $new === null) {
            return;
        }

        if ($new !== null && $old === null) {
            DB::table('permissions')
                ->where('id', $new->id)
                ->update([
                    'name' => 'qmh.force_unlock',
                    'display_name' => 'Paksa Buka Kunci Dokumen Quality Management Hub',
                    'module' => 'qmh',
                    'action' => 'force-unlock',
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($new !== null && $old !== null) {
            $now = now();

            $roleRows = DB::table('role_permissions')
                ->where('permission_id', $new->id)
                ->get(['role']);

            foreach ($roleRows as $row) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $row->role,
                    'permission_id' => $old->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $userRows = DB::table('user_permissions')
                ->where('permission_id', $new->id)
                ->get(['user_id', 'granted']);

            foreach ($userRows as $row) {
                DB::table('user_permissions')->insertOrIgnore([
                    'user_id' => $row->user_id,
                    'permission_id' => $old->id,
                    'granted' => (bool) $row->granted,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('role_permissions')->where('permission_id', $new->id)->delete();
            DB::table('user_permissions')->where('permission_id', $new->id)->delete();
            DB::table('permissions')->where('id', $new->id)->delete();
        }

        DB::table('permissions')
            ->where('name', 'qmh.force_unlock')
            ->update([
                'display_name' => 'Paksa Buka Kunci Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'force-unlock',
                'updated_at' => now(),
            ]);
    }
};
