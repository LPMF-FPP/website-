<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\Sample;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed system settings (always needed)
        $this->call(SystemSettingSeeder::class);
        
        // SEEDING DINONAKTIFKAN - Hanya untuk data riil
        // Uncomment baris di bawah jika perlu generate data testing
        
        /*
        // Create or get admin user
        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin User',
                'role' => 'admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now()
            ]
        );

        // Create test users
        $users = User::factory(5)->create();

        // Create investigators
        $investigators = Investigator::factory(10)->create();

        // Create test requests with relationships
        TestRequest::factory(20)
            ->has(Sample::factory(rand(1, 3)))
            ->create();
        */
    }
}
