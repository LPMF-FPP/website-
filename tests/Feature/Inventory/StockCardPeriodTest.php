<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryStockCardService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCardPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_period_opening_and_transfer_effects_are_exact_with_and_without_location(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $from = InventoryLocation::factory()->create(['is_restricted' => false]);
        $to = InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->movement($viewer, $item, 'RECEIPT', '10.123', null, $from->id, '2026-09-30 23:59:59');
        $transfer = $this->movement($viewer, $item, 'TRANSFER', '2.001', $from->id, $to->id, '2026-10-01 00:00:00');
        $this->movement($viewer, $item, 'ISSUE', '0.122', $to->id, null, '2026-10-01 23:59:59');
        $this->movement($viewer, $item, 'RECEIPT', '9.999', null, $from->id, '2026-10-02 00:00:00');
        $filters = ['item_id' => $item->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-01'];
        $service = app(InventoryStockCardService::class);
        $report = $service->report($filters, $viewer);
        $this->assertSame('10.123', $report['opening_balance']);
        $this->assertSame('10.001', $report['closing_balance']);
        $this->assertSame('0.000', $report['total_in']);
        $this->assertSame('0.122', $report['total_out']);
        $this->assertSame('0.000', $report['rows'][0]['change']);
        $this->assertSame(0.0, $transfer->signed_qty);
        $source = $service->report($filters + ['location_id' => $from->id], $viewer);
        $destination = $service->report($filters + ['location_id' => $to->id], $viewer);
        $this->assertSame('8.122', $source['closing_balance']);
        $this->assertSame('1.879', $destination['closing_balance']);
        $this->assertSame('-2.001', $source['rows'][0]['change']);
        $this->assertSame('2.001', $destination['rows'][0]['change']);
        $this->assertSame('10.001', $service->report(['item_id' => $item->id, 'date_to' => '2026-10-01'], $viewer)['closing_balance']);
        $this->assertSame('20.000', $service->report(['item_id' => $item->id, 'date_from' => '2026-10-01'], $viewer)['closing_balance']);
    }

    public function test_empty_historical_period_preserves_opening_stock_and_does_not_read_current_projection(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id]);
        $this->movement($viewer, $item, 'RECEIPT', '0.101', null, $location->id, '2026-09-01 12:00:00', $lot->id);
        $filters = ['lot_id' => $lot->id, 'date_from' => '2026-09-05', 'date_to' => '2026-09-06'];
        $response = $this->actingAs($viewer)->get(route('inventory.stock-card', $filters));
        $response->assertOk()->assertViewHas('report', function ($report) use ($item): bool {
            return $report['opening_balance'] === '0.101' && $report['closing_balance'] === '0.101'
                && $report['rows'] === [] && $report['historical'] && $report['item']->is($item);
        })->assertSee('0,101')->assertSee('akhir periode');
    }

    public function test_invalid_filters_and_unauthorized_scope_are_denied_for_screen_and_pdf(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $otherLot = InventoryLot::factory()->create();
        $hidden = InventoryLocation::factory()->create(['is_restricted' => true]);
        foreach (['inventory.stock-card', 'inventory.stock-card.print'] as $route) {
            $this->actingAs($viewer)->getJson(route($route, ['item_id' => $item->id, 'date_from' => '2026-10-02', 'date_to' => '2026-10-01']))->assertUnprocessable();
            $this->getJson(route($route, ['item_id' => $item->id, 'date_to' => 'not-a-date']))->assertUnprocessable();
            $this->getJson(route($route, ['item_id' => $item->id, 'lot_id' => $otherLot->id]))->assertForbidden();
            $this->getJson(route($route, ['item_id' => $item->id, 'location_id' => $hidden->id]))->assertForbidden();
        }
    }

    public function test_ordering_pagination_and_search_preserve_full_period_totals(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        for ($i = 0; $i < 51; $i++) {
            $movement = $this->movement($viewer, $item, 'RECEIPT', '0.001', null, $location->id, '2026-10-01 12:00:00');
        }
        $filters = ['item_id' => $item->id, 'date_from' => '2026-10-01', 'page' => 2];
        $this->actingAs($viewer)->get(route('inventory.stock-card', $filters))->assertOk()
            ->assertViewHas('stockCard', fn ($rows) => count($rows) === 1 && $rows[0]['movement']->id === $movement->id)
            ->assertViewHas('report', fn ($report) => $report['closing_balance'] === '0.051')
            ->assertSee('date_from=2026-10-01', false);
        $report = app(InventoryStockCardService::class)->report(['item_id' => $item->id, 'search' => 'impossible-filter'], $viewer);
        $this->assertSame([], $report['rows']);
        $this->assertSame('0.051', $report['closing_balance']);
    }

    public function test_large_fractional_totals_are_formatted_without_float_rounding(): void
    {
        $this->assertSame('123.456.789.012.345,001', InventoryStockCardService::formatQuantity('123456789012345.001'));
        $this->assertSame('-123.456.789.012.345,001', InventoryStockCardService::formatQuantity('-123456789012345.001'));
    }

    public function test_adjustment_and_disposal_directions_share_the_period_contract(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->movement($viewer, $item, 'ADJUST', '5.001', null, $location->id, '2026-09-01 12:00:00');
        $this->movement($viewer, $item, 'ADJUST', '0.002', $location->id, null, '2026-09-01 13:00:00');
        $this->movement($viewer, $item, 'DISPOSE', '1.001', $location->id, null, '2026-09-01 14:00:00');
        $report = app(InventoryStockCardService::class)->report(['item_id' => $item->id], $viewer);
        $this->assertSame('5.001', $report['total_in']);
        $this->assertSame('1.003', $report['total_out']);
        $this->assertSame('3.998', $report['closing_balance']);
    }

    public function test_global_report_aggregates_only_authorized_transfer_endpoint_effects_without_hidden_names(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $visible = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hidden = InventoryLocation::factory()->create(['is_restricted' => true, 'name' => 'Hidden counterpart location']);
        InventoryLocationAccess::create(['user_id' => $viewer->id, 'location_id' => $visible->id, 'can_view' => true, 'can_transact' => false]);
        \App\Models\InventoryBalance::create(['item_id' => $item->id, 'location_id' => $visible->id, 'on_hand_qty' => 2]);
        $this->movement($viewer, $item, 'TRANSFER', '2.001', $hidden->id, $visible->id, '2026-09-01 12:00:00');
        $report = app(InventoryStockCardService::class)->report(['item_id' => $item->id], $viewer);
        $this->assertSame('2.001', $report['closing_balance']);
        $this->assertNull($report['rows'][0]['movement']->fromLocation);
        $this->actingAs($viewer)->get(route('inventory.stock-card', ['item_id' => $item->id]))->assertOk()->assertDontSee($hidden->name);
    }

    private function movement(User $user, InventoryItem $item, string $type, string $qty, ?int $from, ?int $to, string $date, ?int $lotId = null): InventoryMovement
    {
        return InventoryMovement::factory()->create([
            'item_id' => $item->id, 'lot_id' => $lotId, 'movement_type' => $type, 'qty' => $qty,
            'from_location_id' => $from, 'to_location_id' => $to, 'performed_by' => $user->id, 'performed_at' => $date,
        ]);
    }
}
