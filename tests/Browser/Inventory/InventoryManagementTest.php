<?php

namespace Tests\Browser\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InventoryManagementTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_view_inventory_dashboard(): void
    {
        $user = User::factory()->create();
        InventoryItem::factory()->count(5)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori')
                ->assertSee('Inventory')
                ->assertPresent('.inventory-stats');
        });
    }

    public function test_user_can_view_inventory_items_list(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create(['name' => 'Test Reagent XYZ']);

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items')
                ->assertSee('Items')
                ->assertSee($item->name)
                ->assertPresent('table');
        });
    }

    public function test_user_can_create_inventory_item(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items/create')
                ->assertSee('Create Item')
                ->select('item_type', 'REAGENT')
                ->type('name', 'New Test Reagent')
                ->type('brand', 'Test Brand')
                ->type('manufacturer', 'Test Manufacturer')
                ->type('specification', 'Test Spec')
                ->type('uom', 'mL')
                ->type('pack_size', '100')
                ->type('min_stock', '10')
                ->select('storage_condition', 'RT')
                ->press('Create')
                ->waitForText('Item created successfully')
                ->assertSee('Item created successfully');

            $this->assertDatabaseHas('inventory_items', [
                'name' => 'New Test Reagent',
                'brand' => 'Test Brand',
            ]);
        });
    }

    public function test_user_can_update_inventory_item(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create(['name' => 'Original Name']);

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit("/referensi/inventori/items/{$item->id}/edit")
                ->assertSee('Edit Item')
                ->assertInputValue('name', 'Original Name')
                ->type('name', 'Updated Name')
                ->type('min_stock', '20')
                ->press('Update')
                ->waitForText('Item updated successfully')
                ->assertSee('Item updated successfully');

            $item->refresh();
            $this->assertEquals('Updated Name', $item->name);
            $this->assertEquals('20', $item->min_stock);
        });
    }

    public function test_user_can_delete_inventory_item(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items')
                ->assertSee($item->name)
                ->press("delete-item-{$item->id}")
                ->acceptDialog()
                ->waitForText('Item deleted successfully')
                ->assertSee('Item deleted successfully')
                ->assertDontSee($item->name);
        });
    }

    public function test_user_can_search_and_filter_inventory_items(): void
    {
        $user = User::factory()->create();
        $reagent = InventoryItem::factory()->create([
            'item_type' => 'REAGENT',
            'name' => 'Special Reagent ABC',
        ]);
        $consumable = InventoryItem::factory()->create([
            'item_type' => 'CONSUMABLE',
            'name' => 'Regular Consumable XYZ',
        ]);

        $this->browse(function (Browser $browser) use ($user, $reagent, $consumable) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items')
                ->assertSee($reagent->name)
                ->assertSee($consumable->name)
                ->type('search', 'Special')
                ->waitForText($reagent->name)
                ->assertSee($reagent->name)
                ->assertDontSee($consumable->name);

            $browser->visit('/referensi/inventori/items')
                ->select('filter[item_type]', 'CONSUMABLE')
                ->press('Filter')
                ->waitForText($consumable->name)
                ->assertSee($consumable->name)
                ->assertDontSee($reagent->name);
        });
    }

    public function test_user_receives_low_stock_alert(): void
    {
        $user = User::factory()->create();
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::factory()->create(['min_stock' => 50]);

        $item->balances()->create([
            'inventory_location_id' => $location->id,
            'quantity' => 5,
        ]);

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori')
                ->assertSee('Low Stock Alerts')
                ->assertSee($item->name)
                ->assertPresent('.low-stock-warning');
        });
    }
}
