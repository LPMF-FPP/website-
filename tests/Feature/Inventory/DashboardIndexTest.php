<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_items_have_trend_data()
    {
        $user = User::factory()->create();

        // Create item with low stock
        $item = \App\Models\InventoryItem::factory()->create([
            'min_stock' => 10,
            'name' => 'Low Stock Item',
        ]);

        // Balance 0
        \App\Models\InventoryBalance::factory()->create([
            'item_id' => $item->id,
            'on_hand_qty' => 0,
        ]);

        // Usage > min_stock * 2 (High Trend)
        // 10 * 2 = 20. Usage = 25
        \App\Models\InventoryMovement::factory()->create([
            'item_id' => $item->id,
            'movement_type' => 'ISSUE',
            'qty' => 25,
            'uom' => 'PCS',
            'performed_at' => now()->subDays(5),
        ]);

        $this->actingAs($user);
        $response = $this->get(route('inventory.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('lowStockItems', function ($items) use ($item) {
            $first = $items->firstWhere('id', $item->id);

            // Must have 'monthly_usage' attribute and 'trend' attribute
            return $first && $first->monthly_usage == 25 && $first->trend === 'high';
        });
    }

    public function test_ajax_overview_eager_loads_relations()
    {
        $user = User::factory()->create();
        $item = \App\Models\InventoryItem::factory()->create();
        $location = \App\Models\InventoryLocation::factory()->create(['name' => 'Loc A']);
        $lot = \App\Models\InventoryLot::factory()->create(['item_id' => $item->id, 'expiry_date' => now()->addDays(10), 'status' => 'ACTIVE']);

        \App\Models\InventoryBalance::factory()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 10,
        ]);

        $this->actingAs($user);
        $response = $this->getJson(route('inventory.ajax.overview'));

        $response->assertStatus(200);
        $data = $response->json('data.0');

        $this->assertArrayHasKey('balances', $data);
        $this->assertArrayHasKey('location', $data['balances'][0]); // Check nested location
        $this->assertEquals('Loc A', $data['balances'][0]['location']['name']);

        $this->assertArrayHasKey('lots', $data);
        $this->assertEquals($lot->id, $data['lots'][0]['id']);
    }
}
