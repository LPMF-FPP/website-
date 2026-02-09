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
}
