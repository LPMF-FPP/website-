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
                    'pattern' => 'LPMF-{YYYY}{MM}-{INV}-{SEQ:4}',
                    'reset' => 'monthly',
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
                    'pattern' => 'LHU/{YYYY}/{MM}/{TEST}/{SEQ:4}',
                    'reset' => 'monthly',
                    'start_from' => 1,
                    'per_test_type' => true,
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
                    'LHU' => null,
                    'BA_PERMINTAAN' => null,
                    'BA_PENYERAHAN' => null,
                    'TANDA_TERIMA' => null,
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
                    'purge_after_days' => 1825,
                    'export_filename_pattern' => '{DOC}/{YYYY}/{MM}/{SEQ:4}.pdf',
                ],
            ],
            [
                'key' => 'automation',
                'value' => [
                    'auto_generate_supporting_docs' => [
                        'request_letter' => false,
                        'handover_report' => false,
                        'sample_receipt' => false,
                        'test_report' => false,
                    ],
                    'notify_on_issue' => [
                        'email' => false,
                        'whatsapp' => false,
                        'templates' => [
                            'subject' => '[LIMS] Nomor {SCOPE} {NUMBER} terbit',
                            'body' => 'Nomor {SCOPE}: {NUMBER} untuk {REQ}',
                            'whatsapp' => '*[LIMS]* Nomor {SCOPE} {NUMBER} terbit untuk {REQ}',
                        ],
                    ],
                    'whatsapp_recipient' => '',
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

