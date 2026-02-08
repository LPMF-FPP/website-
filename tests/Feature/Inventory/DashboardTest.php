<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_quick_action_widget()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Pengeluaran Cepat'); // Quick Issue tab
        $response->assertSee('Penerimaan Cepat'); // Quick Receipt tab
        $response->assertSee('Transfer Cepat'); // Quick Transfer tab
    }

    public function test_dashboard_always_shows_disposal_widget_even_when_no_eligible_samples(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // No eligible samples created.

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Disposal Sampel');
        $response->assertSee('Kelola Disposal');
    }

    public function test_dashboard_shows_top_movers_by_issue_volume_last_7_days(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $location = InventoryLocation::factory()->create();

        $itemA = InventoryItem::factory()->create(['name' => 'Item A', 'uom' => 'pcs']);
        $itemB = InventoryItem::factory()->create(['name' => 'Item B', 'uom' => 'pcs']);

        InventoryBalance::create([
            'item_id' => $itemA->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 100,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);
        InventoryBalance::create([
            'item_id' => $itemB->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 100,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);

        InventoryMovement::create([
            'movement_type' => 'ISSUE',
            'reference_type' => 'MANUAL',
            'reference_id' => null,
            'item_id' => $itemA->id,
            'lot_id' => null,
            'from_location_id' => $location->id,
            'to_location_id' => null,
            'qty' => 5,
            'uom' => $itemA->uom,
            'unit_cost' => null,
            'performed_by' => $user->id,
            'performed_at' => now()->subDays(1),
            'reason_code' => null,
            'notes' => null,
        ]);
        InventoryMovement::create([
            'movement_type' => 'ISSUE',
            'reference_type' => 'MANUAL',
            'reference_id' => null,
            'item_id' => $itemB->id,
            'lot_id' => null,
            'from_location_id' => $location->id,
            'to_location_id' => null,
            'qty' => 20,
            'uom' => $itemB->uom,
            'unit_cost' => null,
            'performed_by' => $user->id,
            'performed_at' => now()->subDays(2),
            'reason_code' => null,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Barang Paling Boros');
        $response->assertSee('Item B');
    }

    public function test_dashboard_shows_stock_health_section_with_bullet_graph_rows(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $location = InventoryLocation::factory()->create();

        $item = InventoryItem::factory()->create([
            'name' => 'Natrium Klorida',
            'uom' => 'g',
            'min_stock' => 10,
        ]);

        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Kesehatan Stok');
        $response->assertSee('Natrium Klorida');
    }

    public function test_dashboard_disposal_widget_shows_summary_counts(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Sample::factory()->create([
            'disposal_status' => 'pending',
            'disposal_id' => null,
            'disposed_at' => null,
            'testing_completed_at' => now()->subDays(10),
        ]);

        Sample::factory()->create([
            'disposal_status' => 'eligible',
            'disposal_id' => null,
            'disposed_at' => null,
            'testing_completed_at' => now()->subDays(20),
        ]);

        $disposal = SampleDisposal::factory()->create([
            'executed_at' => now()->subDays(2),
        ]);

        Sample::factory()->create([
            'disposal_status' => 'disposed',
            'disposal_id' => $disposal->id,
            'disposed_at' => now()->subDay(),
            'testing_completed_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('data-testid="disposal-finished-count">2', false);
        $response->assertSee('data-testid="disposal-eligible-count">1', false);
        $response->assertSee('data-testid="disposal-disposed-month-count">1', false);
    }
}
