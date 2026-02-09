<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_overview_returns_paginated_items_with_correct_structure()
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();

        // Create items with different stock levels
        // 1. OK Item (20 stock) - Should be first
        $okItem = InventoryItem::factory()->create(['name' => 'A - OK Item', 'min_stock' => 10, 'is_active' => true]);
        InventoryBalance::create([
            'item_id' => $okItem->id,
            'location_id' => InventoryLocation::factory()->create()->id,
            'on_hand_qty' => 20,
        ]);

        // 2. Critical Item (5 stock) - Should be second
        $criticalItem = InventoryItem::factory()->create(['name' => 'B - Critical Item', 'min_stock' => 10, 'is_active' => true]);
        InventoryBalance::create([
            'item_id' => $criticalItem->id,
            'location_id' => InventoryLocation::factory()->create()->id,
            'on_hand_qty' => 5,
        ]);

        // 3. Empty Item (0 stock) - Should be third
        $emptyItem = InventoryItem::factory()->create(['name' => 'C - Empty Item', 'min_stock' => 10, 'is_active' => true]);

        $inactiveItem = InventoryItem::factory()->create(['name' => 'Inactive Item', 'is_active' => false]);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.overview'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'total_stock', 'status'],
                ],
                'current_page',
                'per_page',
                'total',
            ]);

        // Assert sorting: OK item (20) -> Critical (5) -> Empty (0)
        $data = $response->json('data');
        $this->assertEquals('A - OK Item', $data[0]['name']);
        $this->assertEquals('B - Critical Item', $data[1]['name']);
        $this->assertEquals('C - Empty Item', $data[2]['name']);

        // Assert status
        $this->assertEquals('ok', $data[0]['status']);
        $this->assertEquals('critical', $data[1]['status']);
        $this->assertEquals('empty', $data[2]['status']);

        // Assert inactive not included
        $this->assertCount(3, $data);
    }

    public function test_ajax_overview_search_filter()
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();

        InventoryItem::factory()->create(['name' => 'Apple', 'is_active' => true]);
        InventoryItem::factory()->create(['name' => 'Banana', 'is_active' => true]);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.overview', ['q' => 'app']));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Apple', $data[0]['name']);
    }
}
