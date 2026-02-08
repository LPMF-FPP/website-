<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Str::random(16);

        $admin = User::updateOrCreate(
            ['email' => 'labmutufarmapol@gmail.com'],
            [
                'name' => 'Admin LPMF',
                'email' => 'labmutufarmapol@gmail.com',
                'password' => Hash::make($password),
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
        $this->command->warn("Generated password: {$password}");
        $this->command->warn('⚠️  SAVE THIS PASSWORD! It will not be shown again.');
    }
}
