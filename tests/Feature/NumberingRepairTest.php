<?php

namespace Tests\Feature;

use App\Models\NumberingChangeLog;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NumberingRepairTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_get_counter_status(): void
    {
        Sequence::create([
            'scope' => 'ba',
            'bucket' => now()->format('Y-m'),
            'current_value' => 5,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/numbering/repair/ba/status');

        $response->assertOk()
            ->assertJsonStructure([
                'scope',
                'bucket',
                'current_counter',
                'from_max',
                'from_count',
            ]);
    }

    public function test_can_scan_for_problems(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/numbering/repair/ba/scan');

        $response->assertOk()
            ->assertJsonStructure([
                'scope',
                'bucket',
                'counter_status',
                'problems',
                'problem_count' => ['duplicate', 'gap', 'total'],
            ]);
    }

    public function test_can_reset_counter(): void
    {
        // The service uses 'default' bucket when reset setting is not configured
        $bucket = 'default';

        // Delete any existing sequence for this scope/bucket first
        Sequence::where('scope', 'ba')->where('bucket', $bucket)->delete();

        Sequence::create([
            'scope' => 'ba',
            'bucket' => $bucket,
            'current_value' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/settings/numbering/repair/ba/reset', [
                'new_value' => 5,
                'reason' => 'Testing reset functionality',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('number_sequences', [
            'scope' => 'ba',
            'bucket' => $bucket,
            'current_value' => 5,
        ]);

        $this->assertDatabaseHas('numbering_change_logs', [
            'scope' => 'ba',
            'action_type' => 'reset',
            'old_value' => '10',
            'new_value' => '5',
        ]);
    }

    public function test_cannot_reset_with_negative_value(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/settings/numbering/repair/ba/reset', [
                'new_value' => -1,
                'reason' => 'Testing negative value',
            ]);

        $response->assertStatus(422);
    }

    public function test_reason_is_required_for_reset(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/settings/numbering/repair/ba/reset', [
                'new_value' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_can_sync_counter_by_max(): void
    {
        Sequence::create([
            'scope' => 'ba',
            'bucket' => now()->format('Y-m'),
            'current_value' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/settings/numbering/repair/ba/sync', [
                'method' => 'max',
                'reason' => 'Sync to max number',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('numbering_change_logs', [
            'scope' => 'ba',
            'action_type' => 'sync_max',
        ]);
    }

    public function test_can_get_change_logs(): void
    {
        NumberingChangeLog::create([
            'scope' => 'ba',
            'action_type' => 'reset',
            'old_value' => '10',
            'new_value' => '5',
            'reason' => 'Test log',
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/numbering/repair/change-logs');

        $response->assertOk()
            ->assertJsonStructure([
                'logs' => [
                    '*' => ['id', 'scope', 'action_type', 'old_value', 'new_value', 'reason'],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/settings/numbering/repair/ba/status');

        $response->assertUnauthorized();
    }
}
