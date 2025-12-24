<?php

namespace Tests\Feature\Api\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_get_current_numbering_snapshot(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/settings/numbering/current');

        $response->assertOk()
            ->assertJsonStructure([
                'sample_code',
                'ba',
                'lhu',
            ]);
    }

    public function test_can_update_numbering_settings(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'numbering' => [
                'sample_code' => [
                    'pattern' => '{YEAR}-{COUNTER:4}',
                    'reset' => 'yearly',
                    'start_from' => 1,
                ],
                'ba' => [
                    'pattern' => 'BA-{YEAR}-{COUNTER:3}',
                    'reset' => 'yearly',
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/numbering', $payload);

        $response->assertOk()
            ->assertJsonPath('numbering.sample_code.pattern', '{YEAR}-{COUNTER:4}')
            ->assertJsonPath('numbering.ba.pattern', 'BA-{YEAR}-{COUNTER:3}');

        // Check that setting was stored (value is JSON encoded)
        $setting = SystemSetting::where('key', 'numbering.sample_code.pattern')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('{YEAR}-{COUNTER:4}', $setting->value);
    }

    public function test_partial_update_numbering_does_not_fail(): void
    {
        $this->actingAs($this->admin);

        // Set initial config
        SystemSetting::updateOrCreate(
            ['key' => 'numbering.sample_code.pattern'],
            ['value' => '{CODE}-{YEAR}']
        );

        // Partial update - only update ba section
        $payload = [
            'numbering' => [
                'ba' => [
                    'pattern' => 'BA-NEW-{COUNTER:5}',
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/numbering', $payload);

        $response->assertOk();

        // sample_code should remain unchanged
        $sampleCodeSetting = SystemSetting::where('key', 'numbering.sample_code.pattern')->first();
        $this->assertNotNull($sampleCodeSetting);
        $this->assertEquals('{CODE}-{YEAR}', $sampleCodeSetting->value);

        // ba should be updated
        $baSetting = SystemSetting::where('key', 'numbering.ba.pattern')->first();
        $this->assertNotNull($baSetting);
        $this->assertEquals('BA-NEW-{COUNTER:5}', $baSetting->value);
    }

    public function test_can_preview_numbering_pattern(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'scope' => 'sample_code',
            'config' => [
                'numbering' => [
                    'sample_code' => [
                        'pattern' => 'TEST-{YEAR}-{COUNTER:4}',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/settings/numbering/preview', $payload);

        $response->assertOk()
            ->assertJsonStructure(['example']);

        $example = $response->json('example');
        $this->assertNotEmpty($example);
        $this->assertStringContainsString('TEST-', $example);
        // Note: The example might not include {YEAR} placeholder if NumberingService doesn't replace it
    }

    public function test_numbering_requires_authentication(): void
    {
        $response = $this->getJson('/api/settings/numbering/current');

        $response->assertUnauthorized();
    }

    public function test_numbering_requires_authorization(): void
    {
        // Create user without manage-settings permission
        $user = User::factory()->create(['role' => 'investigator']);
        $this->actingAs($user);

        $response = $this->getJson('/api/settings/numbering/current');

        $response->assertForbidden();
    }

    public function test_validates_numbering_pattern_required(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'numbering' => [
                'sample_code' => [
                    'pattern' => '',
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/numbering', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['numbering.sample_code.pattern']);
    }

    public function test_prepares_empty_strings_as_null(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'numbering' => [
                'sample_code' => [
                    'pattern' => 'VALID-{COUNTER:3}',
                    'reset' => '',  // empty string
                    'start_from' => '',  // empty string
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/numbering', $payload);

        $response->assertOk();

        // Should not store empty strings - either null or not stored
        $resetValue = SystemSetting::where('key', 'numbering.sample_code.reset')->first();
        $startFromValue = SystemSetting::where('key', 'numbering.sample_code.start_from')->first();

        $this->assertTrue(
            $resetValue === null || $resetValue->value === null,
            'Reset should be null or not stored'
        );
        
        $this->assertTrue(
            $startFromValue === null || $startFromValue->value === null,
            'Start from should be null or not stored'
        );
    }
}
