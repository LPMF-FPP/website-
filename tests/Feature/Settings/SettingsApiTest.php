<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_settings_overview_returns_sections(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'settings' => [
                    'numbering',
                    'templates' => ['active', 'list'],
                    'branding',
                    'pdf',
                    'localization',
                    'retention' => ['storage_folder_path', 'resolved_storage_path'],
                    'notifications',
                    'security',
                ],
                'options' => ['timezones', 'date_formats', 'number_formats', 'languages'],
            ]);
    }

    public function test_localization_and_retention_can_be_updated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $payload = [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => 'official_docs/archive',
                'purge_after_days' => 90,
                'export_filename_pattern' => '{DOC}/{YYYY}/{SEQ:4}.pdf',
            ],
        ];

        $response = $this->actingAs($user)->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk()
            ->assertJsonPath('retention.storage_folder_path', 'official_docs/archive');
    }

    public function test_branding_update_and_pdf_preview(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $payload = [
            'branding' => [
                'lab_code' => 'LPMF',
                'org_name' => 'Lab Preview',
                'logo_path' => null,
                'primary_color' => '#123456',
                'secondary_color' => '#654321',
                'digital_stamp_path' => null,
                'watermark_path' => null,
            ],
            'pdf' => [
                'header' => [
                    'show' => true,
                    'address' => 'Jl. Preview',
                    'contact' => '021-000',
                    'logo_path' => null,
                    'watermark' => null,
                ],
                'footer' => [
                    'show' => true,
                    'text' => 'Footer sample',
                    'page_numbers' => true,
                ],
                'signature' => [
                    'enabled' => false,
                    'signers' => [],
                ],
                'qr' => [
                    'enabled' => true,
                    'target' => 'request_detail_url',
                    'caption' => 'Scan',
                ],
            ],
        ];

        $this->actingAs($user)->putJson('/api/settings/branding', $payload)
            ->assertOk()
            ->assertJsonPath('branding.org_name', 'Lab Preview');

        $preview = $this->actingAs($user)->post('/api/settings/pdf/preview', $payload);
        $preview->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
