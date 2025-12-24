<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'labmutufarmapol@gmail.com'],
            [
                'name' => 'Admin LPMF',
                'email' => 'labmutufarmapol@gmail.com',
                'password' => Hash::make('LPMFjaya1'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: labmutufarmapol@gmail.com');
        $this->command->info('Password: LPMFjaya1');
    }
}
