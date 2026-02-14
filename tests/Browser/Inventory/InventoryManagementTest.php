<?php

namespace Tests\Browser\Inventory;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InventoryManagementTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_user_can_view_inventory_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori')
                ->waitForText('Dashboard Inventori')
                ->assertSee('Dashboard Inventori')
                ->assertSee('Master Item')
                ->assertSee('Aksi Cepat');
        });
    }

    public function test_user_can_view_inventory_items_list(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $item = InventoryItem::factory()->create(['name' => 'Test Reagent XYZ']);

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items')
                ->waitForText('Master Item Inventori')
                ->assertSee('Master Item Inventori')
                ->assertSee($item->name)
                ->assertPresent('table');
        });
    }

    public function test_user_can_access_create_inventory_item_form(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items/create')
                ->waitForText('Tambah Item Baru')
                ->assertSee('Tambah Item Baru')
                ->assertPresent('select[name="item_type"]')
                ->assertPresent('input[name="name"]')
                ->assertPresent('input[name="brand"]')
                ->assertPresent('input[name="uom"]')
                ->assertPresent('input[name="min_stock"]')
                ->assertPresent('select[name="storage_condition"]');
        });
    }

    public function test_user_can_create_inventory_item(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori/items/create')
                ->waitForText('Tambah Item Baru')
                ->select('item_type', 'REAGENT')
                ->type('name', 'New Test Reagent')
                ->type('brand', 'Test Brand')
                ->type('manufacturer', 'Test Manufacturer')
                ->type('uom', 'mL')
                ->press('Simpan')
                ->waitForText('Item berhasil ditambahkan')
                ->assertSee('Item berhasil ditambahkan');

            $this->assertDatabaseHas('inventory_items', [
                'name' => 'New Test Reagent',
                'brand' => 'Test Brand',
            ]);
        });
    }

    public function test_user_can_update_inventory_item(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $item = InventoryItem::factory()->create(['name' => 'Original Name']);

        $this->browse(function (Browser $browser) use ($user, $item) {
            $browser->loginAs($user)
                ->visit("/referensi/inventori/items/{$item->id}/edit")
                ->waitForText('Edit Item')
                ->assertSee('Edit Item')
                ->assertInputValue('name', 'Original Name')
                ->clear('name')
                ->type('name', 'Updated Name')
                ->press('Simpan Perubahan')
                ->waitForText('Item berhasil diperbarui')
                ->assertSee('Item berhasil diperbarui');

            $item->refresh();
            $this->assertEquals('Updated Name', $item->name);
        });
    }

    public function test_user_can_filter_inventory_items_by_type(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
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
                ->waitForText('Master Item Inventori')
                ->assertSee($reagent->name)
                ->assertSee($consumable->name)
                ->select('type', 'CONSUMABLE')
                ->press('Filter')
                ->waitForText($consumable->name)
                ->assertSee($consumable->name)
                ->assertDontSee($reagent->name);
        });
    }

    public function test_inventory_dashboard_shows_quick_action_buttons(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/referensi/inventori')
                ->waitForText('Dashboard Inventori')
                ->assertSee('Aksi Cepat')
                ->assertSee('Terima')
                ->assertSee('Keluar')
                ->assertSee('Transfer');
        });
    }
}
