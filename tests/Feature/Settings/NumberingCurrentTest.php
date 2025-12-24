<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingCurrentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup initial settings
        SystemSetting::updateOrCreate(
            ['key' => 'numbering.sample_code'],
            ['value' => [
                'pattern' => 'LPMF-{YYYY}{MM}-{INV}-{SEQ:4}',
                'reset' => 'monthly',
                'start_from' => 1
            ]]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'numbering.ba'],
            ['value' => [
                'pattern' => 'BA/{YYYY}/{MM}/{SEQ:4}',
                'reset' => 'monthly',
                'start_from' => 1
            ]]
        );
    }

    public function test_current_numbering_returns_fresh_data_after_update()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Get initial current numbering
        $response = $this->actingAs($user)
            ->getJson('/api/settings/numbering/current')
            ->assertOk();

        $initialSampleCode = $response->json('sample_code.next');
        $this->assertNotEmpty($initialSampleCode);
        $this->assertIsString($initialSampleCode);

        // Update pattern for sample_code
        $this->actingAs($user)
            ->putJson('/api/settings/numbering/sample_code', [
                'pattern' => 'W{SEQ:3}{RM}{YYYY}',
                'reset' => 'yearly',
                'start_from' => 1
            ])
            ->assertOk()
            ->assertJson([
                'scope' => 'sample_code',
                'message' => 'Pengaturan penomoran berhasil disimpan.',
            ]);

        // Verify the pattern was actually updated in database using dot-notated keys
        $patternSetting = SystemSetting::where('key', 'numbering.sample_code.pattern')->first();
        $this->assertNotNull($patternSetting, 'Pattern setting should exist');
        $this->assertEquals('W{SEQ:3}{RM}{YYYY}', $patternSetting->value);

        // Get current numbering again - should reflect new pattern
        $response = $this->actingAs($user)
            ->getJson('/api/settings/numbering/current')
            ->assertOk();

        $updatedSampleCode = $response->json('sample_code.next');

        // Assert that the format changed
        $this->assertNotEquals($initialSampleCode, $updatedSampleCode);
        $this->assertStringStartsWith('W', $updatedSampleCode, "Updated sample code should start with W, got: {$updatedSampleCode}");
        $this->assertStringContainsString(date('Y'), $updatedSampleCode);
    }

    public function test_current_returns_string_values_not_objects()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->getJson('/api/settings/numbering/current')
            ->assertOk();

        $data = $response->json();

        // Verify all scopes return array structure with current/next/pattern
        $scopes = ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'];
        
        foreach ($scopes as $scope) {
            $this->assertArrayHasKey($scope, $data);
            $this->assertIsArray($data[$scope]);
            $this->assertArrayHasKey('next', $data[$scope]);
            $this->assertIsString($data[$scope]['next']);
        }
    }

    public function test_preview_returns_formatted_string()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->postJson('/api/settings/numbering/preview', [
                'scope' => 'sample_code',
                'config' => [
                    'numbering' => [
                        'sample_code' => [
                            'pattern' => 'W{SEQ:3}{RM}{YYYY}'
                        ]
                    ]
                ]
            ])
            ->assertOk()
            ->assertJsonStructure(['example', 'preview', 'extractedValue', 'scope', 'pattern']);

        $preview = $response->json('preview');
        $example = $response->json('example');
        $extractedValue = $response->json('extractedValue');

        // All three should contain the same formatted value
        $this->assertIsString($preview);
        $this->assertNotEmpty($preview);
        $this->assertStringStartsWith('W', $preview);
        $this->assertEquals($preview, $example);
        $this->assertEquals($preview, $extractedValue);
    }

    public function test_preview_handles_various_pattern_formats()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $patterns = [
            'W{SEQ:3}{RM}{YYYY}' => 'W',
            'Farmapol/BA/{RM}/{YYYY}/RIM-{SEQ:3}' => 'Farmapol',
            '{LAB}-{YYYY}-{MM}-{SEQ:4}' => '',  // LAB depends on settings
        ];

        foreach ($patterns as $pattern => $expectedPrefix) {
            $response = $this->actingAs($user)
                ->postJson('/api/settings/numbering/preview', [
                    'scope' => 'sample_code',
                    'config' => [
                        'numbering' => [
                            'sample_code' => [
                                'pattern' => $pattern
                            ]
                        ]
                    ]
                ])
                ->assertOk();

            $preview = $response->json('preview');
            $this->assertNotEmpty($preview, "Preview empty for pattern: {$pattern}");
            
            if ($expectedPrefix) {
                $this->assertStringStartsWith($expectedPrefix, $preview, "Pattern {$pattern} didn't start with {$expectedPrefix}");
            }
        }
    }

    public function test_updateScope_requires_valid_fields()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Missing pattern
        $this->actingAs($user)
            ->putJson('/api/settings/numbering/sample_code', [
                'reset' => 'monthly',
                'start_from' => 1
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pattern']);

        // Invalid reset value
        $this->actingAs($user)
            ->putJson('/api/settings/numbering/sample_code', [
                'pattern' => '{YYYY}-{SEQ:4}',
                'reset' => 'invalid',
                'start_from' => 1
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reset']);

        // Invalid start_from
        $this->actingAs($user)
            ->putJson('/api/settings/numbering/sample_code', [
                'pattern' => '{YYYY}-{SEQ:4}',
                'reset' => 'monthly',
                'start_from' => 0
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_from']);
    }

    public function test_cache_is_properly_invalidated_after_update()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Set a specific pattern
        $oldPattern = 'OLD-{SEQ:4}';
        SystemSetting::updateOrCreate(
            ['key' => 'numbering.sample_code'],
            ['value' => [
                'pattern' => $oldPattern,
                'reset' => 'monthly',
                'start_from' => 1
            ]]
        );

        // Warm cache by calling settings()
        cache()->forget('sys_settings_all'); // Clear first
        $cachedValue = settings('numbering.sample_code');
        $this->assertNotNull($cachedValue, 'Settings should not return null');
        $this->assertIsArray($cachedValue, 'Settings should return an array');
        $this->assertEquals($oldPattern, $cachedValue['pattern']);

        // Update via API
        $newPattern = 'NEW-{SEQ:5}';
        $this->actingAs($user)
            ->putJson('/api/settings/numbering/sample_code', [
                'pattern' => $newPattern,
                'reset' => 'yearly',
                'start_from' => 10
            ])
            ->assertOk();

        // Verify cache was cleared and new value is readable using dot-notated keys
        $freshPattern = settings('numbering.sample_code.pattern');
        $freshReset = settings('numbering.sample_code.reset');
        $freshStartFrom = settings('numbering.sample_code.start_from');

        $this->assertNotNull($freshPattern, 'Fresh settings pattern should not return null');
        $this->assertEquals($newPattern, $freshPattern);
        $this->assertEquals('yearly', $freshReset);
        $this->assertEquals(10, $freshStartFrom);
    }
}
