<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Daftar semua permission yang tersedia dalam sistem.
     */
    private array $permissions = [
        // Dashboard
        ['name' => 'dashboard.view', 'display_name' => 'Lihat Dashboard', 'module' => 'dashboard', 'action' => 'view'],

        // Permintaan
        ['name' => 'permintaan.view', 'display_name' => 'Lihat Permintaan', 'module' => 'permintaan', 'action' => 'view'],
        ['name' => 'permintaan.create', 'display_name' => 'Tambah Permintaan', 'module' => 'permintaan', 'action' => 'create'],
        ['name' => 'permintaan.edit', 'display_name' => 'Edit Permintaan', 'module' => 'permintaan', 'action' => 'edit'],
        ['name' => 'permintaan.delete', 'display_name' => 'Hapus Permintaan', 'module' => 'permintaan', 'action' => 'delete'],

        // Kaji Ulang
        ['name' => 'kaji-ulang.view', 'display_name' => 'Lihat Kaji Ulang', 'module' => 'kaji-ulang', 'action' => 'view'],
        ['name' => 'kaji-ulang.create', 'display_name' => 'Tambah Kaji Ulang', 'module' => 'kaji-ulang', 'action' => 'create'],
        ['name' => 'kaji-ulang.edit', 'display_name' => 'Edit Kaji Ulang', 'module' => 'kaji-ulang', 'action' => 'edit'],
        ['name' => 'kaji-ulang.delete', 'display_name' => 'Hapus Kaji Ulang', 'module' => 'kaji-ulang', 'action' => 'delete'],

        // Pengujian
        ['name' => 'pengujian.view', 'display_name' => 'Lihat Pengujian', 'module' => 'pengujian', 'action' => 'view'],
        ['name' => 'pengujian.create', 'display_name' => 'Tambah Pengujian', 'module' => 'pengujian', 'action' => 'create'],
        ['name' => 'pengujian.edit', 'display_name' => 'Edit Pengujian', 'module' => 'pengujian', 'action' => 'edit'],
        ['name' => 'pengujian.delete', 'display_name' => 'Hapus Pengujian', 'module' => 'pengujian', 'action' => 'delete'],

        // Penyerahan
        ['name' => 'penyerahan.view', 'display_name' => 'Lihat Penyerahan', 'module' => 'penyerahan', 'action' => 'view'],
        ['name' => 'penyerahan.create', 'display_name' => 'Tambah Penyerahan', 'module' => 'penyerahan', 'action' => 'create'],
        ['name' => 'penyerahan.edit', 'display_name' => 'Edit Penyerahan', 'module' => 'penyerahan', 'action' => 'edit'],
        ['name' => 'penyerahan.delete', 'display_name' => 'Hapus Penyerahan', 'module' => 'penyerahan', 'action' => 'delete'],

        // Buku Tamu
        ['name' => 'guest-book.view', 'display_name' => 'Lihat Buku Tamu', 'module' => 'guest-book', 'action' => 'view'],
        ['name' => 'guest-book.create', 'display_name' => 'Tambah Buku Tamu', 'module' => 'guest-book', 'action' => 'create'],
        ['name' => 'guest-book.edit', 'display_name' => 'Edit Buku Tamu', 'module' => 'guest-book', 'action' => 'edit'],
        ['name' => 'guest-book.checkout', 'display_name' => 'Catat Keluar Buku Tamu', 'module' => 'guest-book', 'action' => 'checkout'],
        ['name' => 'guest-book.delete', 'display_name' => 'Hapus Buku Tamu', 'module' => 'guest-book', 'action' => 'delete'],
        ['name' => 'guest-book.export', 'display_name' => 'Export Buku Tamu', 'module' => 'guest-book', 'action' => 'export'],

        // Tracking
        ['name' => 'tracking.view', 'display_name' => 'Lihat Tracking', 'module' => 'tracking', 'action' => 'view'],

        // Pencarian
        ['name' => 'pencarian.view', 'display_name' => 'Lihat Pencarian', 'module' => 'pencarian', 'action' => 'view'],

        // Statistik
        ['name' => 'statistik.view', 'display_name' => 'Lihat Statistik', 'module' => 'statistik', 'action' => 'view'],
        ['name' => 'statistik.export', 'display_name' => 'Export Statistik', 'module' => 'statistik', 'action' => 'export'],

        // Monitoring Suhu
        ['name' => 'monitoring.view', 'display_name' => 'Lihat Monitoring Suhu', 'module' => 'monitoring', 'action' => 'view'],

        // Inventori
        ['name' => 'inventori.view', 'display_name' => 'Lihat Inventori', 'module' => 'inventori', 'action' => 'view'],
        ['name' => 'inventori.create', 'display_name' => 'Tambah Inventori', 'module' => 'inventori', 'action' => 'create'],
        ['name' => 'inventori.edit', 'display_name' => 'Edit Inventori', 'module' => 'inventori', 'action' => 'edit'],
        ['name' => 'inventori.delete', 'display_name' => 'Hapus Inventori', 'module' => 'inventori', 'action' => 'delete'],

        // Changelogs
        ['name' => 'changelogs.view', 'display_name' => 'Lihat Changelogs', 'module' => 'changelogs', 'action' => 'view'],

        // Manajemen Staff
        ['name' => 'analysts.view', 'display_name' => 'Lihat Manajemen Staff', 'module' => 'analysts', 'action' => 'view'],
        ['name' => 'analysts.create', 'display_name' => 'Tambah Staff', 'module' => 'analysts', 'action' => 'create'],
        ['name' => 'analysts.edit', 'display_name' => 'Edit Staff', 'module' => 'analysts', 'action' => 'edit'],
        ['name' => 'analysts.delete', 'display_name' => 'Hapus Staff', 'module' => 'analysts', 'action' => 'delete'],

        // Manajemen Penyidik
        ['name' => 'investigators.view', 'display_name' => 'Lihat Manajemen Penyidik', 'module' => 'investigators', 'action' => 'view'],
        ['name' => 'investigators.edit', 'display_name' => 'Edit Penyidik', 'module' => 'investigators', 'action' => 'edit'],
        ['name' => 'investigators.delete', 'display_name' => 'Hapus Penyidik', 'module' => 'investigators', 'action' => 'delete'],

        // Pengaturan Sistem
        ['name' => 'settings.view', 'display_name' => 'Lihat Pengaturan', 'module' => 'settings', 'action' => 'view'],
        ['name' => 'settings.edit', 'display_name' => 'Edit Pengaturan', 'module' => 'settings', 'action' => 'edit'],

        // Quality Management Hub
        ['name' => 'qmh.view', 'display_name' => 'Lihat Quality Management Hub', 'module' => 'qmh', 'action' => 'view'],
        ['name' => 'qmh.create', 'display_name' => 'Buat Dokumen Quality Management Hub', 'module' => 'qmh', 'action' => 'create'],
        ['name' => 'qmh.report', 'display_name' => 'Lihat Laporan Quality Management Hub', 'module' => 'qmh', 'action' => 'report'],
        ['name' => 'qmh.template.manage', 'display_name' => 'Kelola Template Quality Management Hub', 'module' => 'qmh', 'action' => 'template-manage'],
        ['name' => 'qmh.unlock.force', 'display_name' => 'Paksa Buka Kunci Dokumen Quality Management Hub', 'module' => 'qmh', 'action' => 'unlock-force'],
        ['name' => 'qmh.approve.attest', 'display_name' => 'Attestation Fallback Approve Quality Management Hub', 'module' => 'qmh', 'action' => 'approve-attest'],
        ['name' => 'qmh.rapat.view', 'display_name' => 'Lihat Rapat QMH', 'module' => 'qmh-rapat', 'action' => 'view'],
        ['name' => 'qmh.rapat.view.all', 'display_name' => 'Lihat Semua Rapat QMH', 'module' => 'qmh-rapat', 'action' => 'view-all'],
        ['name' => 'qmh.rapat.create', 'display_name' => 'Buat Rapat QMH', 'module' => 'qmh-rapat', 'action' => 'create'],
        ['name' => 'qmh.rapat.create.all', 'display_name' => 'Buat Rapat QMH untuk Semua Unit', 'module' => 'qmh-rapat', 'action' => 'create-all'],
        ['name' => 'qmh.rapat.edit', 'display_name' => 'Edit Rapat QMH', 'module' => 'qmh-rapat', 'action' => 'edit'],
        ['name' => 'qmh.rapat.delete', 'display_name' => 'Hapus Rapat QMH', 'module' => 'qmh-rapat', 'action' => 'delete'],
        ['name' => 'qmh.audit.view', 'display_name' => 'Lihat Audit QMH', 'module' => 'qmh-audit', 'action' => 'view'],
        ['name' => 'qmh.audit.view.all', 'display_name' => 'Lihat Semua Audit QMH', 'module' => 'qmh-audit', 'action' => 'view-all'],
        ['name' => 'qmh.audit.create', 'display_name' => 'Buat Audit QMH', 'module' => 'qmh-audit', 'action' => 'create'],
        ['name' => 'qmh.audit.create.all', 'display_name' => 'Buat Audit QMH untuk Semua Unit', 'module' => 'qmh-audit', 'action' => 'create-all'],
        ['name' => 'qmh.audit.edit', 'display_name' => 'Edit Audit QMH', 'module' => 'qmh-audit', 'action' => 'edit'],
        ['name' => 'qmh.audit.delete', 'display_name' => 'Hapus Audit QMH', 'module' => 'qmh-audit', 'action' => 'delete'],
        ['name' => 'qmh.kum.view', 'display_name' => 'Lihat KUM QMH', 'module' => 'qmh-kum', 'action' => 'view'],
        ['name' => 'qmh.kum.view.all', 'display_name' => 'Lihat Semua KUM QMH', 'module' => 'qmh-kum', 'action' => 'view-all'],
        ['name' => 'qmh.kum.create', 'display_name' => 'Buat KUM QMH', 'module' => 'qmh-kum', 'action' => 'create'],
        ['name' => 'qmh.kum.create.all', 'display_name' => 'Buat KUM QMH untuk Semua Unit', 'module' => 'qmh-kum', 'action' => 'create-all'],
        ['name' => 'qmh.kum.edit', 'display_name' => 'Edit KUM QMH', 'module' => 'qmh-kum', 'action' => 'edit'],
        ['name' => 'qmh.kum.delete', 'display_name' => 'Hapus KUM QMH', 'module' => 'qmh-kum', 'action' => 'delete'],
        ['name' => 'action-item:verify', 'display_name' => 'Verifikasi Action Item Governance', 'module' => 'qmh-governance', 'action' => 'verify'],
        ['name' => 'action-item:close', 'display_name' => 'Tutup Action Item Governance', 'module' => 'qmh-governance', 'action' => 'close'],
        ['name' => 'action-item:reopen', 'display_name' => 'Buka Ulang Action Item Governance', 'module' => 'qmh-governance', 'action' => 'reopen'],

        // Reminders
        ['name' => 'reminders.view', 'display_name' => 'Lihat Reminders', 'module' => 'reminders', 'action' => 'view'],
        ['name' => 'reminders.edit', 'display_name' => 'Edit Reminders', 'module' => 'reminders', 'action' => 'edit'],
    ];

    /**
     * Default permission per role.
     */
    private array $roleDefaults = [
        'investigator' => [
            'dashboard.view',
            'permintaan.view',
            'kaji-ulang.view',
            'pengujian.view',
            'penyerahan.view',
            'tracking.view',
            'pencarian.view',
            'statistik.view',
            'changelogs.view',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'analis' => [
            'dashboard.view',
            'permintaan.view', 'permintaan.create', 'permintaan.edit',
            'kaji-ulang.view',
            'pengujian.view', 'pengujian.create', 'pengujian.edit',
            'penyerahan.view',
            'tracking.view',
            'pencarian.view',
            'statistik.view',
            'monitoring.view',
            'inventori.view',
            'changelogs.view',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'penyelia' => [
            'dashboard.view',
            'permintaan.view', 'permintaan.create', 'permintaan.edit',
            'kaji-ulang.view', 'kaji-ulang.create', 'kaji-ulang.edit',
            'pengujian.view', 'pengujian.create', 'pengujian.edit', 'pengujian.delete',
            'penyerahan.view', 'penyerahan.create', 'penyerahan.edit',
            'tracking.view',
            'pencarian.view',
            'statistik.view', 'statistik.export',
            'monitoring.view',
            'inventori.view', 'inventori.create', 'inventori.edit',
            'changelogs.view',
            'analysts.view',
            'settings.view',
            'qmh.view',
            'qmh.rapat.view',
            'qmh.audit.view',
            'qmh.kum.view',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'manajer_teknis' => [
            'dashboard.view',
            'permintaan.view', 'permintaan.create', 'permintaan.edit', 'permintaan.delete',
            'kaji-ulang.view', 'kaji-ulang.create', 'kaji-ulang.edit', 'kaji-ulang.delete',
            'pengujian.view', 'pengujian.create', 'pengujian.edit', 'pengujian.delete',
            'penyerahan.view', 'penyerahan.create', 'penyerahan.edit', 'penyerahan.delete',
            'tracking.view',
            'pencarian.view',
            'statistik.view', 'statistik.export',
            'monitoring.view',
            'inventori.view', 'inventori.create', 'inventori.edit', 'inventori.delete',
            'changelogs.view',
            'analysts.view', 'analysts.create', 'analysts.edit',
            'settings.view', 'settings.edit',
            'reminders.view', 'reminders.edit',
            'qmh.view', 'qmh.create', 'qmh.report', 'qmh.template.manage', 'qmh.unlock.force', 'qmh.approve.attest',
            'qmh.rapat.view', 'qmh.rapat.view.all', 'qmh.rapat.create', 'qmh.rapat.create.all', 'qmh.rapat.edit', 'qmh.rapat.delete',
            'qmh.audit.view', 'qmh.audit.view.all', 'qmh.audit.create', 'qmh.audit.create.all', 'qmh.audit.edit', 'qmh.audit.delete',
            'qmh.kum.view', 'qmh.kum.view.all', 'qmh.kum.create', 'qmh.kum.create.all', 'qmh.kum.edit', 'qmh.kum.delete',
            'action-item:verify', 'action-item:close', 'action-item:reopen',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'admin' => [
            // Admin mendapat semua permission
            'dashboard.view',
            'permintaan.view', 'permintaan.create', 'permintaan.edit', 'permintaan.delete',
            'kaji-ulang.view', 'kaji-ulang.create', 'kaji-ulang.edit', 'kaji-ulang.delete',
            'pengujian.view', 'pengujian.create', 'pengujian.edit', 'pengujian.delete',
            'penyerahan.view', 'penyerahan.create', 'penyerahan.edit', 'penyerahan.delete',
            'tracking.view',
            'pencarian.view',
            'statistik.view', 'statistik.export',
            'monitoring.view',
            'inventori.view', 'inventori.create', 'inventori.edit', 'inventori.delete',
            'changelogs.view',
            'analysts.view', 'analysts.create', 'analysts.edit', 'analysts.delete',
            'investigators.view', 'investigators.edit', 'investigators.delete',
            'settings.view', 'settings.edit',
            'reminders.view', 'reminders.edit',
            'qmh.view', 'qmh.create', 'qmh.report', 'qmh.template.manage', 'qmh.unlock.force', 'qmh.approve.attest',
            'qmh.rapat.view', 'qmh.rapat.view.all', 'qmh.rapat.create', 'qmh.rapat.create.all', 'qmh.rapat.edit', 'qmh.rapat.delete',
            'qmh.audit.view', 'qmh.audit.view.all', 'qmh.audit.create', 'qmh.audit.create.all', 'qmh.audit.edit', 'qmh.audit.delete',
            'qmh.kum.view', 'qmh.kum.view.all', 'qmh.kum.create', 'qmh.kum.create.all', 'qmh.kum.edit', 'qmh.kum.delete',
            'action-item:verify', 'action-item:close', 'action-item:reopen',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'supervisor' => [
            // Supervisor sama dengan admin
            'dashboard.view',
            'permintaan.view', 'permintaan.create', 'permintaan.edit', 'permintaan.delete',
            'kaji-ulang.view', 'kaji-ulang.create', 'kaji-ulang.edit', 'kaji-ulang.delete',
            'pengujian.view', 'pengujian.create', 'pengujian.edit', 'pengujian.delete',
            'penyerahan.view', 'penyerahan.create', 'penyerahan.edit', 'penyerahan.delete',
            'tracking.view',
            'pencarian.view',
            'statistik.view', 'statistik.export',
            'monitoring.view',
            'inventori.view', 'inventori.create', 'inventori.edit', 'inventori.delete',
            'changelogs.view',
            'analysts.view', 'analysts.create', 'analysts.edit', 'analysts.delete',
            'settings.view', 'settings.edit',
            'reminders.view', 'reminders.edit',
            'qmh.view', 'qmh.create', 'qmh.report', 'qmh.template.manage', 'qmh.unlock.force', 'qmh.approve.attest',
            'qmh.rapat.view', 'qmh.rapat.view.all', 'qmh.rapat.create', 'qmh.rapat.create.all', 'qmh.rapat.edit', 'qmh.rapat.delete',
            'qmh.audit.view', 'qmh.audit.view.all', 'qmh.audit.create', 'qmh.audit.create.all', 'qmh.audit.edit', 'qmh.audit.delete',
            'qmh.kum.view', 'qmh.kum.view.all', 'qmh.kum.create', 'qmh.kum.create.all', 'qmh.kum.edit', 'qmh.kum.delete',
            'action-item:verify', 'action-item:close', 'action-item:reopen',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'analyst' => [
            // analyst sama dengan analis
            'dashboard.view',
            'permintaan.view', 'permintaan.create', 'permintaan.edit',
            'kaji-ulang.view',
            'pengujian.view', 'pengujian.create', 'pengujian.edit',
            'penyerahan.view',
            'tracking.view',
            'pencarian.view',
            'statistik.view',
            'monitoring.view',
            'inventori.view',
            'changelogs.view',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'lab_analyst' => [
            // lab_analyst sama dengan analis
            'dashboard.view',
            'permintaan.view', 'permintaan.create', 'permintaan.edit',
            'kaji-ulang.view',
            'pengujian.view', 'pengujian.create', 'pengujian.edit',
            'penyerahan.view',
            'tracking.view',
            'pencarian.view',
            'statistik.view',
            'monitoring.view',
            'inventori.view',
            'changelogs.view',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
        'petugas_lab' => [
            // petugas_lab akses terbatas
            'dashboard.view',
            'pengujian.view',
            'tracking.view',
            'pencarian.view',
            'monitoring.view',
            'inventori.view',
            'changelogs.view',
            'guest-book.view', 'guest-book.create', 'guest-book.edit', 'guest-book.checkout', 'guest-book.delete', 'guest-book.export',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('PermissionSeeder tidak boleh dijalankan di production. Gunakan migration sinkronisasi permission.');
        }

        // Buat semua permission
        foreach ($this->permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        $this->command->info('Permissions created successfully.');

        // Buat role permissions
        foreach ($this->roleDefaults as $role => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();

                if ($permission) {
                    RolePermission::updateOrCreate(
                        [
                            'role' => $role,
                            'permission_id' => $permission->id,
                        ]
                    );
                }
            }
        }

        User::query()->eachById(
            fn (User $user) => $user->clearPermissionCache()
        );

        $this->command->info('Role permissions created successfully.');
    }
}
