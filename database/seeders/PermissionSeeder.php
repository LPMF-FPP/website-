<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
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
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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

        $this->command->info('Role permissions created successfully.');
    }
}
