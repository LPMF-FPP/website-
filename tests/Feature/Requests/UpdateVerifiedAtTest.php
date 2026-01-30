<?php

namespace Tests\Feature\Requests;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateVerifiedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_verified_at(): void
    {
        $testRequest = TestRequest::factory()->create();

        $response = $this->patch(route('requests.update-verified-at', $testRequest), [
            'verified_at' => '2026-01-15',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_update_verified_at(): void
    {
        $user = User::factory()->create();
        $testRequest = TestRequest::factory()->create(['verified_at' => null]);

        $response = $this->actingAs($user)
            ->patch(route('requests.update-verified-at', $testRequest), [
                'verified_at' => '2026-01-15',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('test_requests', [
            'id' => $testRequest->id,
            'verified_at' => '2026-01-15',
        ]);
    }

    public function test_verified_at_requires_valid_date(): void
    {
        $user = User::factory()->create();
        $testRequest = TestRequest::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('requests.update-verified-at', $testRequest), [
                'verified_at' => 'invalid-date',
            ]);

        $response->assertSessionHasErrors('verified_at');
    }

    public function test_verified_at_is_required(): void
    {
        $user = User::factory()->create();
        $testRequest = TestRequest::factory()->create();

        $response = $this->actingAs($user)
            ->patch(route('requests.update-verified-at', $testRequest), [
                'verified_at' => '',
            ]);

        $response->assertSessionHasErrors('verified_at');
    }
}
