<?php

namespace Database\Seeders;

use App\Models\Reminder;
use Illuminate\Database\Seeder;

class ReminderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. ISO Countdown
        Reminder::updateOrCreate(
            ['type' => 'iso_countdown'],
            [
                'name' => 'ISO 17025:2017 Countdown',
                'description' => 'Penghitung mundur harian menuju Surveillance ISO 17025:2017',
                'is_enabled' => true,
                'schedule_time' => '07:00:00',
                'schedule_days' => 'daily',
                'message_template' => "🎯 *ISO 17025:2017 Surveillance*\n\n📅 Target: {target_date}\n⏳ Sisa: *{days_remaining} hari*\n\n{motivation_message}",
                'metadata' => [
                    'target_date' => '2026-08-15',
                ],
            ]
        );

        // 2. Temperature Morning
        Reminder::updateOrCreate(
            ['type' => 'temp_morning'],
            [
                'name' => 'Pengingat Suhu Pagi',
                'description' => 'Pengingat input suhu ruangan pagi hari',
                'is_enabled' => true,
                'schedule_time' => '08:00:00',
                'schedule_days' => 'daily',
                'message_template' => "🌡️ *Pengingat Pencatatan Suhu*\n\n⏰ Waktu: Pagi (08:00)\n📝 Silakan input suhu ruangan dengan format:\n\n/suhu [lokasi] [suhu]",
                'metadata' => null,
            ]
        );

        // 3. Temperature Afternoon
        Reminder::updateOrCreate(
            ['type' => 'temp_afternoon'],
            [
                'name' => 'Pengingat Suhu Siang',
                'description' => 'Pengingat input suhu ruangan siang hari',
                'is_enabled' => true,
                'schedule_time' => '14:00:00',
                'schedule_days' => 'daily',
                'message_template' => "🌡️ *Pengingat Pencatatan Suhu*\n\n⏰ Waktu: Siang (14:00)\n📝 Silakan input suhu ruangan dengan format:\n\n/suhu [lokasi] [suhu]",
                'metadata' => null,
            ]
        );
    }
}
