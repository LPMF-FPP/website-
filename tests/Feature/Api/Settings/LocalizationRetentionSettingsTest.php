<?php

namespace Tests\Feature\Api\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationRetentionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_update_localization_settings(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk()
            ->assertJsonPath('localization.timezone', 'Asia/Jakarta')
            ->assertJsonPath('localization.language', 'id');

        $setting = SystemSetting::where('key', 'locale.timezone')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('Asia/Jakarta', $setting->value);
    }

    public function test_can_update_retention_settings(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => 'storage/app/farmapol',
                'purge_after_days' => 365,
                'export_filename_pattern' => '{DATE}-{TYPE}.pdf',
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk()
            ->assertJsonPath('retention.storage_driver', 'local')
            ->assertJsonPath('retention.purge_after_days', 365);

        $setting = SystemSetting::where('key', 'retention.purge_after_days')->first();
        $this->assertNotNull($setting);
        $this->assertEquals(365, $setting->value);
    }

    public function test_storage_folder_path_accepts_valid_paths(): void
    {
        $this->actingAs($this->admin);

        $validPaths = [
            'storage/app/farmapol',
            'documents/official',
            'uploads/2025/test',
            'a_b-c/d_e-f',
        ];

        foreach ($validPaths as $path) {
            $payload = [
                'retention' => [
                    'storage_folder_path' => $path,
                ],
            ];

            $response = $this->putJson('/api/settings/localization-retention', $payload);

            $response->assertOk("Failed for path: {$path}");
        }
    }

    public function test_storage_folder_path_rejects_absolute_paths(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'retention' => [
                'storage_folder_path' => '/absolute/path/not/allowed',
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['retention.storage_folder_path']);
    }

    public function test_storage_folder_path_rejects_directory_traversal(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'retention' => [
                'storage_folder_path' => 'uploads/../../../etc/passwd',
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['retention.storage_folder_path']);
    }

    public function test_storage_folder_path_is_normalized(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'retention' => [
                'storage_folder_path' => '/storage/app/farmapol/',
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertUnprocessable(); // Should fail validation (absolute path)
    }

    public function test_partial_update_localization_only(): void
    {
        $this->actingAs($this->admin);

        // Set initial retention
        SystemSetting::updateOrCreate(
            ['key' => 'retention.purge_after_days'],
            ['value' => 180]
        );

        // Update only localization
        $payload = [
            'localization' => [
                'timezone' => 'Asia/Makassar',
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk();

        // Retention should remain unchanged
        $setting = SystemSetting::where('key', 'retention.purge_after_days')->first();
        $this->assertNotNull($setting);
        $this->assertEquals(180, $setting->value);
    }

    public function test_partial_update_retention_only(): void
    {
        $this->actingAs($this->admin);

        // Set initial localization
        SystemSetting::updateOrCreate(
            ['key' => 'locale.timezone'],
            ['value' => 'Asia/Jakarta']
        );

        // Update only retention
        $payload = [
            'retention' => [
                'purge_after_days' => 90,
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk();

        // Localization should remain unchanged
        $setting = SystemSetting::where('key', 'locale.timezone')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('Asia/Jakarta', $setting->value);
    }

    public function test_purge_days_can_be_null(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'retention' => [
                'purge_after_days' => null,
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk();

        // Should be deleted or null
        $setting = SystemSetting::where('key', 'retention.purge_after_days')->first();
        $this->assertTrue($setting === null || $setting->value === null);
    }

    public function test_validates_purge_days_minimum(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'retention' => [
                'purge_after_days' => 10,  // less than 30
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['retention.purge_after_days']);
    }

    public function test_prepares_empty_strings_as_null(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'retention' => [
                'storage_driver' => 'local',
                'purge_after_days' => '',  // empty string
                'export_filename_pattern' => '',  // empty string
            ],
        ];

        $response = $this->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk();

        // Should not store empty strings
        $purge = SystemSetting::where('key', 'retention.purge_after_days')->first();
        $pattern = SystemSetting::where('key', 'retention.export_filename_pattern')->first();

        $this->assertTrue($purge === null || $purge->value === null);
        $this->assertTrue($pattern === null || $pattern->value === null);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->putJson('/api/settings/localization-retention', [
            'localization' => ['timezone' => 'Asia/Jakarta'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_requires_authorization(): void
    {
        $user = User::factory()->create(['role' => 'investigator']);
        $this->actingAs($user);

        $response = $this->putJson('/api/settings/localization-retention', [
            'localization' => ['timezone' => 'Asia/Jakarta'],
        ]);

        $response->assertForbidden();
    }
}
