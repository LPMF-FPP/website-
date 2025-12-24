<?php

namespace Tests\Feature\Api\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingScopeSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_single_numbering_scope()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->putJson('/api/settings/numbering/sample_code', [
            'pattern' => 'SMP-{YYYY}-{MM}-{N:4}',
            'reset' => 'monthly',
            'start_from' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'scope',
                'config',
                'message',
            ])
            ->assertJson([
                'scope' => 'sample_code',
                'config' => [
                    'pattern' => 'SMP-{YYYY}-{MM}-{N:4}',
                    'reset' => 'monthly',
                    'start_from' => 1,
                ],
            ]);
    }

    public function test_validates_required_fields_for_scope_save()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->putJson('/api/settings/numbering/sample_code', [
            'pattern' => '',
            'reset' => '',
            'start_from' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pattern', 'reset', 'start_from']);
    }

    public function test_validates_reset_period_enum()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->putJson('/api/settings/numbering/ba', [
            'pattern' => 'BA-{YYYY}-{N:4}',
            'reset' => 'invalid_period',
            'start_from' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reset']);
    }

    public function test_validates_start_from_minimum()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->putJson('/api/settings/numbering/tracking', [
            'pattern' => 'RESI-{N:5}',
            'reset' => 'yearly',
            'start_from' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_from']);
    }

    public function test_rejects_invalid_scope()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->putJson('/api/settings/numbering/invalid_scope', [
            'pattern' => 'TEST-{N:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'errors' => ['scope' => ['Invalid scope']],
            ]);
    }

    public function test_requires_authentication()
    {
        $response = $this->putJson('/api/settings/numbering/sample_code', [
            'pattern' => 'SMP-{YYYY}-{N:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_requires_admin_role()
    {
        $user = User::factory()->create(['role' => 'analyst']);

        $response = $this->actingAs($user)->putJson('/api/settings/numbering/sample_code', [
            'pattern' => 'SMP-{YYYY}-{N:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_can_save_different_scopes_independently()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Save sample_code
        $response1 = $this->actingAs($user)->putJson('/api/settings/numbering/sample_code', [
            'pattern' => 'SMP-{YYYY}-{N:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ]);
        
        $response1->assertStatus(200)
            ->assertJson([
                'config' => [
                    'pattern' => 'SMP-{YYYY}-{N:4}',
                    'reset' => 'yearly',
                    'start_from' => 1,
                ],
            ]);

        // Save ba independently
        $response2 = $this->actingAs($user)->putJson('/api/settings/numbering/ba', [
            'pattern' => 'BA-{YYYY}-{MM}-{N:4}',
            'reset' => 'monthly',
            'start_from' => 100,
        ]);
        
        $response2->assertStatus(200)
            ->assertJson([
                'config' => [
                    'pattern' => 'BA-{YYYY}-{MM}-{N:4}',
                    'reset' => 'monthly',
                    'start_from' => 100,
                ],
            ]);
    }
}
