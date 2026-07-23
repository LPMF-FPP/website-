<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'guest-book.view', 'display_name' => 'Lihat Buku Tamu', 'module' => 'guest-book', 'action' => 'view'],
            ['name' => 'guest-book.create', 'display_name' => 'Tambah Buku Tamu', 'module' => 'guest-book', 'action' => 'create'],
            ['name' => 'guest-book.edit', 'display_name' => 'Edit Buku Tamu', 'module' => 'guest-book', 'action' => 'edit'],
            ['name' => 'guest-book.checkout', 'display_name' => 'Catat Keluar Buku Tamu', 'module' => 'guest-book', 'action' => 'checkout'],
            ['name' => 'guest-book.delete', 'display_name' => 'Hapus Buku Tamu', 'module' => 'guest-book', 'action' => 'delete'],
            ['name' => 'guest-book.export', 'display_name' => 'Export Buku Tamu', 'module' => 'guest-book', 'action' => 'export'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm['name']],
                $perm
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'guest-book.view',
            'guest-book.create',
            'guest-book.edit',
            'guest-book.checkout',
            'guest-book.delete',
            'guest-book.export',
        ])->delete();
    }
};
