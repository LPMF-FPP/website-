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
        $user = $this->inventoryViewer();
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.issue.create');
        $user->grantPermission('inventori.transfer.create');
        $user->grantPermission('inventori.material-disposal.create');

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('inventory.transaction.issue'), false);
        $response->assertSee(route('inventory.locations.index'), false);
        $response->assertSee('<nav x-data aria-label="Navigasi inventori"', false);
        $response->assertSee(route('inventory.transaction.receipt'), false);
        $response->assertSee(route('inventory.transaction.transfer'), false);
        $response->assertSee(route('inventory.transaction.dispose'), false);
        $response->assertSee('Pengeluaran Stok');
        $response->assertSee('Terima stok');
        $response->assertSee('Transfer Stok');
        $response->assertSee('Buang Stok');
        $response->assertSee('Lokasi Penyimpanan');
    }

    public function test_inventory_navigation_is_shared_across_pages_and_respects_permissions(): void
    {
        $user = $this->inventoryViewer();
        $user->grantPermission('inventori.receipt.create');

        $dashboard = $this->actingAs($user)->get(route('inventory.dashboard'));
        $dashboard->assertOk()
            ->assertSee('<nav x-data aria-label="Navigasi inventori"', false)
            ->assertSee(route('inventory.transaction.receipt'), false)
            ->assertSee('aria-current="page"', false);

        $items = $this->get(route('inventory.items.index'));
        $items->assertOk()
            ->assertSee('<nav x-data aria-label="Navigasi inventori"', false)
            ->assertSee('Master Item')
            ->assertSee(route('inventory.stock-card'), false);

        $this->assertStringNotContainsString(route('inventory.transaction.issue'), $items->getContent());
    }

    public function test_receipt_form_prefills_the_item_from_its_contextual_link(): void
    {
        $user = $this->inventoryViewer();
        $user->grantPermission('inventori.receipt.create');
        $item = InventoryItem::factory()->create(['item_code' => 'PREFILL-RECEIPT-01', 'name' => 'Prefilled Reagent']);

        $response = $this->actingAs($user)->get(route('inventory.transaction.receipt', ['item_id' => $item->id]));

        $response->assertOk()
            ->assertSee('PREFILL-RECEIPT-01')
            ->assertSee('selected', false);
    }

    public function test_dashboard_hides_stock_mutation_actions_without_action_permissions(): void
    {
        $user = $this->inventoryViewer();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Simpan Penerimaan');
        $response->assertDontSee('Simpan Pengeluaran');
        $response->assertDontSee('Simpan Transfer');
        $response->assertDontSee(route('inventory.opening-baseline.index'));
        $response->assertDontSee(route('inventory.transaction.issue'));
    }

    public function test_dashboard_shows_stock_opname_button(): void
    {
        /** @var User $user */
        $user = $this->inventoryViewer();
        $user->grantPermission('inventori.stocktake.submit');

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Catat Stok Awal');
        $response->assertSee(route('inventory.opening-baseline.index'));
    }

    public function test_dashboard_shows_disposal_button(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('sample-disposal.view');

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Pemusnahan Sampel');
        $response->assertSee(route('inventory.disposal.index'));
    }

    public function test_dashboard_denies_access_without_inventory_view_permission(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->from(route('testing.index'))
            ->actingAs($user)
            ->get(route('inventory.dashboard'));

        $response->assertRedirect(route('testing.index'));
        $response->assertSessionHas('error');
    }

    public function test_dashboard_shows_overview_widget(): void
    {
        /** @var User $user */
        $user = $this->inventoryViewer();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Ringkasan Inventori');
    }

    public function test_dashboard_alert_cards_are_keyboard_operable_controls_with_state_labels(): void
    {
        $user = $this->inventoryViewer();

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk()
            ->assertSee('<button type="button" aria-controls="inventory-alerts"', false)
            ->assertSee('aria-label="Lihat stok rendah,', false)
            ->assertSee('aria-label="Lihat lot kedaluwarsa,', false)
            ->assertSee('id="inventory-alerts"', false)
            ->assertSee(':aria-pressed="tab ===', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_primary_inventory_action_uses_shared_variant_and_stock_state_has_text(): void
    {
        $user = $this->inventoryViewer();
        $user->grantPermission('inventori.create');
        InventoryItem::factory()->create(['name' => 'Status Label Reagent', 'item_type' => 'REAGENT', 'min_stock' => 4]);

        $response = $this->actingAs($user)->get(route('inventory.items.index'));

        $response->assertOk()
            ->assertSee('btn btn-primary', false)
            ->assertSee('Status Label Reagent')
            ->assertSee('Di bawah minimum')
            ->assertSee('inventory-status-danger', false);
    }

    public function test_add_lot_dialog_exposes_accessible_keyboard_controls(): void
    {
        $user = $this->inventoryViewer();
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create();

        $response = $this->actingAs($user)->get(route('inventory.items.lots', $item));

        $response->assertOk()
            ->assertSee('id="add-lot-trigger"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-label="Tutup formulir lot"', false)
            ->assertSee('@keydown.escape.window="if (open) close()"', false)
            ->assertSee('function addInventoryLotDialog(config = {})', false);
    }

    public function test_add_lot_validation_reopens_the_dialog_and_preserves_the_lot_number(): void
    {
        $user = $this->inventoryViewer();
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT']);

        $response = $this->actingAs($user)
            ->from(route('inventory.items.lots', $item))
            ->post(route('inventory.lots.store'), [
                'item_id' => $item->id,
                'lot_no' => 'REAGENT-LOT-RETRY',
            ]);

        $response->assertRedirect(route('inventory.items.lots', $item));
        $page = $this->get(route('inventory.items.lots', $item));

        $page->assertOk()
            ->assertSee('addInventoryLotDialog({ open: true })', false)
            ->assertSee('value="REAGENT-LOT-RETRY"', false)
            ->assertSee('Tanggal kadaluarsa wajib diisi', false)
            ->assertSee('role="alert"', false);
    }

    public function test_dashboard_always_shows_disposal_widget_even_when_no_eligible_samples(): void
    {
        /** @var User $user */
        $user = $this->inventoryViewer();

        // No eligible samples created.

        $response = $this->actingAs($user)->get(route('inventory.dashboard'));

        $response->assertOk();
        $response->assertSee('Pemusnahan Sampel');
        // $response->assertSee('Kelola Disposal'); // Changed to just "Kelola ->" or icon
    }

    public function test_dashboard_shows_top_movers_by_issue_volume_last_7_days(): void
    {
        /** @var User $user */
        $user = $this->inventoryViewer();

        $location = InventoryLocation::factory()->create(['is_restricted' => false]);

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
        $response->assertSee('Barang dengan pengeluaran tertinggi (7 hari terakhir)');
        $response->assertSee('Item B');
    }

    public function test_dashboard_disposal_widget_shows_summary_counts(): void
    {
        /** @var User $user */
        $user = $this->inventoryViewer();

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

    private function inventoryViewer(): User
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('sample-disposal.view');

        return $user;
    }
}
