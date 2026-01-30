<?php

namespace Tests\Feature\Settings;

use App\Enums\InstrumentUsageType;
use App\Models\Instrument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstrumentRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_instrument_requirements_with_valid_data(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $instrument = Instrument::create([
            'code' => 'INST-001',
            'name' => 'Test Instrument',
            'category' => 'PREP',
            'is_active' => true,
        ]);

        $payload = [
            'requirements_by_method' => [
                'uv_vis' => [
                    [
                        'instrument_id' => $instrument->id,
                        'mandatory' => true,
                        'usage_type' => InstrumentUsageType::PREP->value,
                        'sequence' => 1,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/settings/instrument-requirements', $payload);

        $response->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('method_instrument_requirements', [
            'method_code' => 'uv_vis',
            'instrument_id' => $instrument->id,
            'usage_type' => InstrumentUsageType::PREP->value,
        ]);
    }

    public function test_cannot_save_instrument_requirements_with_invalid_enum(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $instrument = Instrument::create([
            'code' => 'INST-002',
            'name' => 'Test Instrument 2',
            'category' => 'PREP',
            'is_active' => true,
        ]);

        $payload = [
            'requirements_by_method' => [
                'uv_vis' => [
                    [
                        'instrument_id' => $instrument->id,
                        'mandatory' => true,
                        'usage_type' => 'INVALID_TYPE',
                        'sequence' => 1,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/settings/instrument-requirements', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['requirements_by_method.uv_vis.0.usage_type']);
    }
}
