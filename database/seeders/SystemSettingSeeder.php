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
                    'org_name' => 'Laboratorium Pengujian Mutu Farmasi Kepolisian',
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
            [
                'key' => 'locale',
                'value' => [
                    'timezone' => 'Asia/Jakarta',
                    'date_format' => 'DD/MM/YYYY',
                    'number_format' => '1.234,56',
                    'language' => 'id',
                ],
            ],
            [
                'key' => 'retention',
                'value' => [
                    'storage_driver' => 'local',
                    'base_path' => 'official_docs/',
                    'storage_folder_path' => 'official_docs/',
                    'purge_after_days' => 1825,
                    'export_filename_pattern' => '{DOC}/{YYYY}/{MM}/{SEQ:4}.pdf',
                ],
            ],
            [
                'key' => 'notifications',
                'value' => [
                    'email' => [
                        'enabled' => true,
                        'default_recipient' => 'lab@example.test',
                        'subject' => '[LIMS] Pesan notifikasi',
                        'body' => 'Pesan pengujian siap dikirim.',
                    ],
                    'whatsapp' => [
                        'enabled' => false,
                        'default_target' => '',
                        'message' => '*[LIMS]* Pesan percobaan notifikasi.',
                    ],
                ],
            ],
            [
                'key' => 'security.roles',
                'value' => [
                    'can_manage_settings' => ['admin', 'supervisor'],
                    'can_issue_number' => ['admin', 'supervisor', 'analis'],
                ],
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
