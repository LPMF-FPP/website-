<?php

namespace Tests\Feature\Settings;

use App\Models\Instrument;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstrumentRequirementsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_can_save_valid_instrument_requirements()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $instrument = Instrument::create([
            'code' => 'TEST-001',
            'name' => 'Test Instrument',
            'category' => 'Test',
            'is_active' => true,
        ]);

        $payload = [
            'requirements_by_method' => [
                'uv_vis' => [
                    [
                        'instrument_id' => $instrument->id,
                        'mandatory' => true,
                        'usage_type' => 'PREP',
                        'sequence' => 1,
                    ],
                ],
                'invalid_method' => [
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
            ->postJson('/settings/instrument-requirements', $payload)
            ->assertOk();

        $this->assertDatabaseHas('method_instrument_requirements', [
            'method_code' => 'uv_vis',
            'instrument_id' => $instrument->id,
        ]);

        $this->assertDatabaseMissing('method_instrument_requirements', [
            'method_code' => 'invalid_method',
        ]);
    }
}
