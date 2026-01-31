<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class ConsolidatedReportSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'consolidated_report.auto_generate_enabled' => true,
            'consolidated_report.notify_on_generate' => true,
            'consolidated_report.default_signers' => [
                [
                    'role' => 'Pembuat',
                    'name' => 'Nama Pembuat',
                    'position' => 'Jabatan Pembuat',
                    'nip' => '123456789',
                ],
                [
                    'role' => 'Pemeriksa',
                    'name' => 'Nama Pemeriksa',
                    'position' => 'Jabatan Pemeriksa',
                    'nip' => '123456789',
                ],
                [
                    'role' => 'Pengesah',
                    'name' => 'Nama Pengesah',
                    'position' => 'Jabatan Pengesah',
                    'nip' => '123456789',
                ],
            ],
            'consolidated_report.default_narratives.opening' => 'Berdasarkan hasil kegiatan pengujian sampel pada periode {period_label}, dengan ini kami sampaikan laporan gabungan sebagai berikut:',
            'consolidated_report.default_narratives.closing' => 'Demikian laporan ini dibuat untuk dapat dipergunakan sebagaimana mestinya. Terima kasih atas perhatian dan kerjasamanya.',
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
