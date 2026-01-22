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
        $admin = User::updateOrCreate(
            ['email' => 'labmutufarmapol@gmail.com'],
            [
                'name' => 'Admin LPMF',
                'email' => 'labmutufarmapol@gmail.com',
                'password' => Hash::make('LPMFjaya1'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (Permission::count() === 0) {
            $this->call(PermissionSeeder::class);
        }

        $permissionIds = Permission::pluck('id');

        foreach ($permissionIds as $permissionId) {
            UserPermission::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'granted' => true,
                ]
            );
        }

        $admin->clearPermissionCache();

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: labmutufarmapol@gmail.com');
        $this->command->info('Password: LPMFjaya1');
    }
}
