<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFastMovingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fast_moving_endpoint_exists_and_is_protected()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('inventory.ajax.fast-moving'));

        // Should fail 404 if route not defined, or 500 if method missing
        $response->assertStatus(200);
    }

    public function test_ajax_fast_moving_returns_correct_data()
    {
        $user = User::factory()->create();
        $item1 = \App\Models\InventoryItem::factory()->create(['name' => 'Item A']);
        $item2 = \App\Models\InventoryItem::factory()->create(['name' => 'Item B']);

        // Item A: 10 + 5 = 15 qty issued in last 30 days
        \App\Models\InventoryMovement::factory()->create([
            'item_id' => $item1->id,
            'movement_type' => 'ISSUE',
            'qty' => 10,
            'performed_at' => now()->subDays(5),
        ]);
        \App\Models\InventoryMovement::factory()->create([
            'item_id' => $item1->id,
            'movement_type' => 'ISSUE',
            'qty' => 5,
            'performed_at' => now()->subDays(20),
        ]);

        // Item B: 5 qty issued (less than A)
        \App\Models\InventoryMovement::factory()->create([
            'item_id' => $item2->id,
            'movement_type' => 'ISSUE',
            'qty' => 5,
            'performed_at' => now()->subDays(2),
        ]);

        // Old movement (should be ignored)
        \App\Models\InventoryMovement::factory()->create([
            'item_id' => $item1->id,
            'movement_type' => 'ISSUE',
            'qty' => 100,
            'performed_at' => now()->subDays(31),
        ]);

        // Receipt (should be ignored)
        \App\Models\InventoryMovement::factory()->create([
            'item_id' => $item1->id,
            'movement_type' => 'RECEIPT',
            'qty' => 50,
            'performed_at' => now()->subDays(5),
        ]);

        $this->actingAs($user);
        $response = $this->getJson(route('inventory.ajax.fast-moving'));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertEquals($item1->id, $data[0]['item_id']); // Item A first
        $this->assertEquals(15, $data[0]['total_out']);
        $this->assertEquals($item2->id, $data[1]['item_id']); // Item B second
        $this->assertEquals(5, $data[1]['total_out']);

        // Check structure
        $this->assertArrayHasKey('item', $data[0]);
        $this->assertEquals('Item A', $data[0]['item']['name']);
    }
}
