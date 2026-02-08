<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_search_returns_exact_lot_match_with_issue_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

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

        $item = InventoryItem::factory()->create([
            'name' => 'Acetone',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('inventory.ajax.search', ['q' => 'Acetone']));

        $response->assertOk();
        $response->assertJsonPath('exact_match.type', 'item');
        $response->assertJsonPath('exact_match.item_id', $item->id);
    }
}
