<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_ajax_overview_returns_paginated_items_with_correct_structure()
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->grantPermission('inventori.view');

        // Create items with different stock levels
        // 1. OK Item (20 stock) - Should be first
        $okItem = InventoryItem::factory()->create(['name' => 'A - OK Item', 'min_stock' => 10, 'is_active' => true]);
        InventoryBalance::create([
            'item_id' => $okItem->id,
            'location_id' => InventoryLocation::factory()->create(['is_restricted' => false])->id,
            'on_hand_qty' => 20,
        ]);

        // 2. Critical Item (5 stock) - Should be second
        $criticalItem = InventoryItem::factory()->create(['name' => 'B - Critical Item', 'min_stock' => 10, 'is_active' => true]);
        InventoryBalance::create([
            'item_id' => $criticalItem->id,
            'location_id' => InventoryLocation::factory()->create(['is_restricted' => false])->id,
            'on_hand_qty' => 5,
        ]);

        // 3. Empty Item (0 stock) - Should be third
        $emptyItem = InventoryItem::factory()->create(['name' => 'C - Empty Item', 'min_stock' => 10, 'is_active' => true]);

        $inactiveItem = InventoryItem::factory()->create(['name' => 'Inactive Item']);
        $inactiveItem->forceFill(['is_active' => false])->save();

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
        $user->grantPermission('inventori.view');

        InventoryItem::factory()->create(['name' => 'Apple', 'is_active' => true]);
        InventoryItem::factory()->create(['name' => 'Banana', 'is_active' => true]);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.overview', ['q' => 'app']));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Apple', $data[0]['name']);
    }

    public function test_ajax_overview_keeps_search_filter_in_pagination_links(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->grantPermission('inventori.view');

        foreach (range(1, 11) as $index) {
            InventoryItem::factory()->create(['name' => sprintf('Filtered reagent %02d', $index), 'is_active' => true]);
        }

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.overview', ['q' => 'Filtered']));

        $response->assertOk();
        $this->assertStringContainsString('q=Filtered', $response->json('next_page_url'));
    }

    public function test_ajax_overview_matches_the_low_stock_boundary_at_minimum(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create(['name' => 'Minimum boundary item', 'min_stock' => 10, 'is_active' => true]);
        InventoryBalance::create([
            'item_id' => $item->id,
            'location_id' => InventoryLocation::factory()->create(['is_restricted' => false])->id,
            'on_hand_qty' => 10,
        ]);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.overview'));

        $response->assertOk()->assertJsonPath('data.0.status', 'ok');
    }

    public function test_dashboard_overview_exposes_accessible_loading_empty_error_and_detail_states(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->grantPermission('inventori.view');

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk()
            ->assertSee('role="status" aria-live="polite"', false)
            ->assertSee('Memuat ringkasan stok.')
            ->assertSee('role="alert"', false)
            ->assertSee('Ringkasan inventori gagal dimuat. Periksa koneksi lalu coba lagi.')
            ->assertSee('Tidak ada item yang sesuai dengan pencarian.')
            ->assertSee(':aria-expanded="expandedRows.includes(item.id)', false)
            ->assertSee('aria-controls="`inventory-item-detail-${item.id}`"', false);
    }
}
