<?php

namespace Tests\Feature\Api;

use App\Models\Sample;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class NumberingRepairCompactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('manage-settings', fn () => true);
    }

    public function test_compact_preview_returns_expected_plan_for_simple_gap_case(): void
    {
        settings_fake([
            'numbering.sample_code.pattern' => 'SC{SEQ:3}',
            'numbering.sample_code.reset' => 'never',
        ], true);

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        Sample::factory()->create([
            'sample_code' => 'SC001',
            'created_at' => now()->subMinutes(2),
        ]);
        Sample::factory()->create([
            'sample_code' => 'SC003',
            'created_at' => now()->subMinutes(1),
        ]);

        Sequence::create([
            'scope' => 'sample_code',
            'bucket' => 'default',
            'current_value' => 3,
        ]);

        $response = $this->getJson('/api/settings/numbering/repair/sample_code/compact-preview');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('rename_count', 1);
        $response->assertJsonPath('locked_count', 0);
        $response->assertJsonPath('counter_before', 3);
        $response->assertJsonPath('counter_after', 2);
        $response->assertJsonPath('examples.0.from', 'SC003');
        $response->assertJsonPath('examples.0.to', 'SC002');
    }

    public function test_compact_applies_and_updates_sample_codes(): void
    {
        settings_fake([
            'numbering.sample_code.pattern' => 'SC{SEQ:3}',
            'numbering.sample_code.reset' => 'never',
        ], true);

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $s1 = Sample::factory()->create([
            'sample_code' => 'SC001',
            'created_at' => now()->subMinutes(2),
        ]);
        $s3 = Sample::factory()->create([
            'sample_code' => 'SC003',
            'created_at' => now()->subMinutes(1),
        ]);

        Sequence::create([
            'scope' => 'sample_code',
            'bucket' => 'default',
            'current_value' => 3,
        ]);

        $response = $this->postJson('/api/settings/numbering/repair/sample_code/compact', [
            'reason' => 'Test compaction',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('rename_count', 1);
        $response->assertJsonPath('counter_before', 3);
        $response->assertJsonPath('counter_after', 2);

        $this->assertDatabaseHas('samples', [
            'id' => $s1->id,
            'sample_code' => 'SC001',
        ]);
        $this->assertDatabaseHas('samples', [
            'id' => $s3->id,
            'sample_code' => 'SC002',
        ]);

        $this->assertDatabaseHas('number_sequences', [
            'scope' => 'sample_code',
            'bucket' => 'default',
            'current_value' => 2,
        ]);
    }
}
