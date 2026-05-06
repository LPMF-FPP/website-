<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed system settings (always needed)
        $this->call(SystemSettingSeeder::class);
    }
}
