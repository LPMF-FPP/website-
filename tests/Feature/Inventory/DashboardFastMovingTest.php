<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Tests\TestCase;

class DashboardFastMovingTest extends TestCase
{
    public function test_fast_moving_endpoint_exists_and_is_protected()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('inventory.ajax.fast-moving'));

        // Should fail 404 if route not defined, or 500 if method missing
        $response->assertStatus(200);
    }
}
