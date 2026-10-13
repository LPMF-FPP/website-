<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_ajax_search_returns_exact_lot_match_with_issue_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.issue.create');

        $lot = InventoryLot::factory()->create([
            'lot_no' => 'LOT-1234',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.search', ['q' => 'LOT-1234']));

        $response->assertOk();
        $response->assertJsonPath('exact_match.type', 'lot');
        $response->assertJsonPath('exact_match.lot_id', $lot->id);
        $response->assertJsonPath('exact_match.item_id', $lot->item_id);
        $response->assertJsonStructure(['results']);
    }

    public function test_ajax_search_returns_exact_item_match_with_issue_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.issue.create');

        $item = InventoryItem::factory()->create([
            'name' => 'Acetone',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.search', ['q' => 'Acetone']));

        $response->assertOk();
        $response->assertJsonPath('exact_match.type', 'item');
        $response->assertJsonPath('exact_match.item_id', $item->id);
    }

    public function test_view_only_search_opens_stock_card_without_an_issue_link(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create(['name' => 'Read Only Reagent']);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.search', ['q' => $item->name]));

        $response->assertOk()
            ->assertJsonPath('exact_match.issue_url', null)
            ->assertJsonPath('exact_match.view_url', route('inventory.stock-card', ['item_id' => $item->id]));
    }
}
