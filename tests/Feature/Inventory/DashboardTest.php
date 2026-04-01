<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\SampleTestProcess;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_dashboard_shows_quick_action_widget()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Keluar'); // Quick Issue tab
        $response->assertSee('Terima'); // Quick Receipt tab
        $response->assertSee('Transfer'); // Quick Transfer tab
    }

    public function test_dashboard_shows_stock_opname_button(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Stok Opname');
        $response->assertSee(route('inventory.transaction.stocktake'));
    }

    public function test_dashboard_shows_disposal_button(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Pemusnahan Sampel');
        $response->assertSee(route('inventory.disposal.index'));
    }

    public function test_dashboard_hides_disposal_button_without_inventory_view_permission(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('inventory.disposal.index'));
    }

    public function test_dashboard_shows_overview_widget(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Ringkasan Inventori');
    }

    public function test_dashboard_always_shows_disposal_widget_even_when_no_eligible_samples(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // No eligible samples created.

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Disposal Sampel');
        // $response->assertSee('Kelola Disposal'); // Changed to just "Kelola ->" or icon
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

        $eligibleSample = Sample::factory()->create([
            'disposal_status' => 'pending',
            'disposal_id' => null,
            'disposed_at' => null,
            'testing_completed_at' => now()->subDays(91),
        ]);
        SampleTestProcess::factory()->create([
            'sample_id' => $eligibleSample->id,
            'stage' => 'interpretation',
            'completed_at' => now()->subDays(91),
            'metadata' => ['lhu_number' => 'LHU-2025-1001'],
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
        $response->assertSee('data-testid="disposal-finished-count"', false);
        $response->assertSee('2', false); // Verify value exists
        $response->assertSee('data-testid="disposal-eligible-count"', false);
        $response->assertSee('1', false);
        $response->assertSee('data-testid="disposal-disposed-month-count"', false);
    }
}
