<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = 'password'; // Use standard predictable password for dev/test

        $seededAdmins = [
            [
                'email' => 'labmutufarmapol@gmail.com',
                'name' => 'Admin LPMF',
            ],
        ];

        if (app()->environment(['local', 'testing'])) {
            $seededAdmins[] = [
                'email' => 'admin@example.com',
                'name' => 'Admin QA',
            ];
            $seededAdmins[] = [
                'email' => 'test@example.com',
                'name' => 'Test Admin',
            ];
        }

        $admins = collect($seededAdmins)->map(
            fn (array $adminData) => User::updateOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'password' => Hash::make($password),
                    'role' => 'admin-lpmf',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            )
        );

        if (Permission::count() === 0) {
            $this->call(PermissionSeeder::class);
        }

        $permissionIds = Permission::pluck('id');

        foreach ($admins as $admin) {
            foreach ($permissionIds as $permissionId) {
                UserPermission::updateOrCreate(
                    ['user_id' => $admin->id, 'permission_id' => $permissionId],
                    ['granted' => true]
                );
            }

            $admin->clearPermissionCache();
        }

        $emails = $admins->pluck('email')->implode(', ');

        $this->command->info('Admin users created successfully!');
        $this->command->info("Emails: {$emails}");
        $this->command->info('Password for all seeded admins in current environment: password');
    }
}
