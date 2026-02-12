<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NumberingRepairService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NumberingRepairInheritedSequenceTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Check if role attribute exists in factory, otherwise use spatie/laravel-permission if available or just create user
        // Based on context, we'll try to create an admin user.
        // If the factory doesn't support 'role', we might need to attach it differently,
        // but the prompt explicitly used: User::factory()->create(['role' => 'admin']);
        // verifying UserFactory showed it doesn't have 'role' in definition, but it might be a fillable on the model.
        // We will stick to the prompt's provided code for the test file.

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_lhu_is_marked_as_inherited_sequence_scope(): void
    {
        $this->assertTrue(
            in_array('lhu', NumberingRepairService::INHERITED_SEQUENCE_SCOPES, true)
        );
    }

    public function test_ba_penyerahan_is_marked_as_inherited_sequence_scope(): void
    {
        $this->assertTrue(
            in_array('ba_penyerahan', NumberingRepairService::INHERITED_SEQUENCE_SCOPES, true)
        );
    }

    public function test_ba_is_not_inherited_sequence_scope(): void
    {
        $this->assertFalse(
            in_array('ba', NumberingRepairService::INHERITED_SEQUENCE_SCOPES, true)
        );
    }

    public function test_scan_lhu_returns_zero_gaps(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/numbering/repair/lhu/scan');

        $response->assertOk()
            ->assertJsonPath('problem_count.gap', 0)
            ->assertJsonPath('uses_inherited_sequence', true);
    }

    public function test_scan_ba_penyerahan_returns_zero_gaps(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/numbering/repair/ba_penyerahan/scan');

        $response->assertOk()
            ->assertJsonPath('problem_count.gap', 0)
            ->assertJsonPath('uses_inherited_sequence', true);
    }

    public function test_scan_ba_does_not_have_inherited_flag(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/numbering/repair/ba/scan');

        $response->assertOk()
            ->assertJsonPath('uses_inherited_sequence', false);
    }

    public function test_uses_inherited_sequence_helper(): void
    {
        $service = app(NumberingRepairService::class);

        $this->assertTrue($service->usesInheritedSequence('lhu'));
        $this->assertTrue($service->usesInheritedSequence('ba_penyerahan'));
        $this->assertFalse($service->usesInheritedSequence('ba'));
        $this->assertFalse($service->usesInheritedSequence('sample_code'));
        $this->assertFalse($service->usesInheritedSequence('tracking'));
    }
}
