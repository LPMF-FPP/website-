<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'numbering.sample_code',
                'value' => [
                    'pattern' => 'W{SEQ:3}{RM}{YYYY}',
                    'reset' => 'yearly',
                    'start_from' => 1,
                ],
            ],
            [
                'key' => 'numbering.ba',
                'value' => [
                    'pattern' => 'BA/{YYYY}/{MM}/{SEQ:4}',
                    'reset' => 'monthly',
                    'start_from' => 1,
                ],
            ],
            [
                'key' => 'numbering.lhu',
                'value' => [
                    'pattern' => 'LHU-{YYYY}-{SEQ:4}',
                    'reset' => 'yearly',
                    'start_from' => 1,
                ],
            ],
            [
                'key' => 'numbering.ba_penyerahan',
                'value' => [
                    'pattern' => 'LPMF/BA/{SEQ:3}/Rah/{YYYY}',
                    'reset' => 'yearly',
                    'start_from' => 1,
                ],
            ],
            [
                'key' => 'numbering.tracking',
                'value' => [
                    'pattern' => 'LPMF{SEQ:3}{MM}{YY}',
                    'reset' => 'monthly',
                    'start_from' => 1,
                ],
            ],
            [
                'key' => 'branding',
                'value' => [
                    'lab_code' => 'LPMF',
                    'org_name' => 'Pusat Kedokteran dan Kesehatan Polri',
                    'lab_name' => 'Laboratorium Pengujian Mutu Farmasi Kepolisian',
                    'address' => 'Jl. Contoh No.1, Jakarta',
                    'phone' => '+62-21-xxxxxxx',
                    'email' => 'lab@example.test',
                    'website' => 'lpmf.local',
                    'logo_path' => null,
                    'primary_color' => '#0A5FD3',
                    'secondary_color' => '#0EC5FF',
                    'digital_stamp_path' => null,
                ],
            ],
            [
                'key' => 'pdf',
                'value' => [
                    'header' => [
                        'show' => true,
                        'address' => 'Jl. Contoh No.1, Jakarta',
                        'contact' => '+62-21-xxxxxxx',
                        'logo_path' => null,
                        'watermark' => null,
                    ],
                    'footer' => [
                        'show' => true,
                        'text' => 'Rahasia - Hanya untuk keperluan resmi',
                        'page_numbers' => true,
                    ],
                    'signature' => [
                        'enabled' => true,
                        'signers' => [
                            ['title' => 'Kepala Lab', 'name' => null, 'stamp_path' => null],
                        ],
                    ],
                    'qr' => [
                        'enabled' => true,
                        'target' => 'request_detail_url',
                        'caption' => 'Scan untuk verifikasi',
                    ],
                ],
            ],
            [
                'key' => 'templates.active',
                'value' => [
                    'lhu' => null,
                    'ba_penerimaan' => null,
                    'ba_penyerahan' => null,
                ],
            ],
            // Updated to flat keys for locale to match SettingsWriter behavior and prevent duplicate key issues
            [
                'key' => 'locale.timezone',
                'value' => 'Asia/Jakarta',
            ],
            [
                'key' => 'locale.date_format',
                'value' => 'DD/MM/YYYY',
            ],
            [
                'key' => 'locale.number_format',
                'value' => '1.234,56',
            ],
            [
                'key' => 'locale.language',
                'value' => 'id',
            ],
            [
                'key' => 'retention',
                'value' => [
                    'storage_driver' => 'public',
                    'base_path' => 'official_docs/',
                    'storage_folder_path' => 'official_docs/',
                    'purge_after_days' => 1825,
                    'export_filename_pattern' => '{DOC}/{YYYY}/{MM}/{SEQ:4}.pdf',
                ],
            ],
            // Flattened notifications settings to prevent JSON vs Flat Key conflicts
            [
                'key' => 'notifications.email.enabled',
                'value' => true,
            ],
            [
                'key' => 'notifications.email.default_recipient',
                'value' => 'lab@example.test',
            ],
            [
                'key' => 'notifications.email.subject',
                'value' => '[LIMS] Pesan notifikasi',
            ],
            [
                'key' => 'notifications.email.body',
                'value' => 'Pesan pengujian siap dikirim.',
            ],
            [
                'key' => 'notifications.whatsapp.enabled',
                'value' => true,
            ],
            [
                'key' => 'notifications.whatsapp.base_url',
                'value' => env('WHATSAPP_API_URL', 'http://localhost:3000'),
            ],
            [
                'key' => 'notifications.whatsapp.basic_user',
                'value' => env('WHATSAPP_BASIC_USER', 'lpmf'),
            ],
            [
                'key' => 'notifications.whatsapp.basic_pass',
                'value' => env('WHATSAPP_BASIC_PASS') ? encrypt(env('WHATSAPP_BASIC_PASS')) : (env('APP_ENV') === 'local' ? encrypt('lpmfjaya1') : null),
            ],
            [
                'key' => 'notifications.whatsapp.device_id',
                'value' => env('WHATSAPP_DEVICE_ID', '03663e24-efdb-48fe-961d-456436bfb219'),
            ],
            [
                'key' => 'notifications.whatsapp.default_target',
                'value' => '',
            ],
            [
                'key' => 'notifications.whatsapp.message',
                'value' => '*[LIMS]* Pesan percobaan notifikasi.',
            ],
            [
                'key' => 'security.roles',
                'value' => [
                    'can_manage_settings' => ['admin', 'supervisor'],
                    'can_manage_users' => ['admin', 'manajer_teknis'],
                    'can_issue_number' => ['admin', 'supervisor', 'analis'],
                ],
            ],
            [
                'key' => 'lab.head_title',
                'value' => 'KAFARMAPOL',
            ],
            [
                'key' => 'lab.head_name',
                'value' => 'KUSWARDANI, S.Si., Apt., M.Farm',
            ],
            [
                'key' => 'lab.head_rank',
                'value' => 'KOMBES POL.',
            ],
            [
                'key' => 'lab.head_nrp',
                'value' => '70040687',
            ],
            [
                'key' => 'lab.head_signature',
                'value' => 'images/ttd-kafarmapol.png',
            ],
        ];

        foreach ($settings as $setting) {
            // Skip null values to prevent NOT NULL constraint violation on 'value' column
            if ($setting['value'] === null) {
                continue;
            }

            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
