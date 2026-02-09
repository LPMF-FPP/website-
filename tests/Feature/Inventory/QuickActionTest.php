<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_issue_submission()
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = User::factory()->create();
        $this->actingAs($user); // Authenticate properly

        $item = InventoryItem::factory()->create(['uom' => 'pcs']);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id]);
        $location = InventoryLocation::factory()->create();

        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 100,
        ]);

        $response = $this->post(route('inventory.transaction.issue'), [
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'qty' => 5,
            'reference_type' => 'MANUAL',
            'notes' => 'Quick issue from dashboard',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('inventory_movements', [
            'item_id' => $item->id,
            'qty' => 5,
            'movement_type' => 'ISSUE',
            'notes' => 'Quick issue from dashboard',
        ]);
    }
}
