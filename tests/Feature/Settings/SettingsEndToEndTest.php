<?php

namespace Tests\Feature\Settings;

use App\Models\Instrument;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed settings to ensure defaults exist
        $this->seed(SystemSettingSeeder::class);
        // Clear cache to avoid stale settings
        settings_forget_cache();
    }

    public function test_full_settings_flow_end_to_end()
    {
        // 1. Setup User
        $user = User::factory()->create(['role' => 'admin']);

        // 2. Visit /settings and check for initial data
        $response = $this->actingAs($user)->get('/settings');
        $response->assertOk();
        // We assert that the window.__SETTINGS_INITIAL_DATA__ variable is present in the HTML
        $response->assertSee('window.__SETTINGS_INITIAL_DATA__', false);

        // 3. Simulate saving Numbering settings
        // Endpoint: PUT /api/settings/numbering
        // The NumberingController expects a 'numbering' array with scope keys.
        $numberingPayload = [
            'numbering' => [
                'sample_code' => [
                    'pattern' => 'LAB/{Y}/{N}',
                    'reset' => 'yearly',
                    'start_from' => 1,
                ],
            ],
        ];

        $this->actingAs($user)
            ->putJson('/api/settings/numbering', $numberingPayload)
            ->assertStatus(200);

        // 4. Simulate saving Branding settings
        // Endpoint: PUT /api/settings/branding
        $brandingPayload = [
            'branding' => [
                'org_name' => 'End To End Test Lab',
                'lab_code' => 'E2E',
                'primary_color' => '#000000',
            ],
            'pdf' => [
                'header' => ['show' => true],
                'footer' => ['show' => true],
            ],
        ];
        $this->actingAs($user)
            ->putJson('/api/settings/branding', $brandingPayload)
            ->assertOk()
            ->assertJsonPath('branding.org_name', 'End To End Test Lab');

        // 5. Simulate saving Notifications settings
        // Endpoint: PUT /api/settings/notifications-security
        $notificationsPayload = [
            'notifications' => [
                'email_enabled' => true,
                'whatsapp_enabled' => false,
            ],
            'security' => [
                'session_lifetime' => 120,
            ],
        ];
        $this->actingAs($user)
            ->putJson('/api/settings/notifications-security', $notificationsPayload)
            ->assertOk();

        // 6. Simulate saving Instrument Requirements
        // Endpoint: POST /settings/instrument-requirements
        // Need an instrument first
        $instrument = Instrument::create([
            'code' => 'INST-E2E',
            'name' => 'E2E Instrument',
            'category' => 'General',
            'is_active' => true,
        ]);

        $instrumentPayload = [
            'requirements_by_method' => [
                'uv_vis' => [
                    [
                        'instrument_id' => $instrument->id,
                        'mandatory' => true,
                        'usage_type' => 'PREP',
                        'sequence' => 1,
                    ],
                ],
            ],
        ];
        $this->actingAs($user)
            ->postJson('/settings/instrument-requirements', $instrumentPayload)
            ->assertOk();

        // 7. Simulate saving IKU settings
        // Endpoint: PUT /api/settings/iku
        $ikuPayload = [
            'targets' => [
                ['year' => 2024, 'target' => 100],
            ],
            // Assuming payload structure. If it fails, I'll need to check IkuSettingsController.
            // Let's assume it accepts a generic array or specific keys.
            // A common IKU payload might be just the array or wrapped.
            // Let's try to update a hypothetical 'target_year'.
            'iku_target_defaults' => 85,
        ];

        // Note: IKU structure is specific. Let's inspect IkuSettingsPageTest or Controller if this fails.
        // But for now, I'll assume a simple key-value update is accepted if it's a flexible settings store,
        // or a specific structure if it's rigid.
        // Given I don't have the IKU test file content, I'll try a minimal valid payload if possible.
        // "targets" seems plausible.

        $this->actingAs($user)
            ->putJson('/api/settings/iku', $ikuPayload)
             // Assert 200 or 201.
            ->assertSuccessful();

    }
}
