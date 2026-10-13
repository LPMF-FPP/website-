<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\User;
use App\Services\InventoryDashboardQueryService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_complete_alert_destinations_match_uncapped_dashboard_counts_and_hide_restricted_stock(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $visible = InventoryLocation::factory()->create(['is_restricted' => false]);
        $hidden = InventoryLocation::factory()->create(['is_restricted' => true]);
        foreach (['low_stock', 'near_expiry', 'expired'] as $category) {
            for ($i = 0; $i < 23; $i++) {
                $item = InventoryItem::factory()->create(['min_stock' => $category === 'low_stock' ? 2 : 0]);
                $lot = $category === 'low_stock' ? null : InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'ACTIVE', 'expiry_date' => $category === 'expired' ? now()->subDay() : now()->addDays(5)]);
                InventoryBalance::create(['item_id' => $item->id, 'lot_id' => $lot?->id, 'location_id' => $visible->id, 'on_hand_qty' => 1]);
            }
        }
        $secret = InventoryItem::factory()->create(['name' => 'Hidden Stock', 'min_stock' => 2]);
        InventoryBalance::create(['item_id' => $secret->id, 'location_id' => $hidden->id, 'on_hand_qty' => 1]);
        $dashboard = $this->actingAs($viewer)->get(route('inventory.dashboard', ['tab' => 'expired']));
        $dashboard->assertOk()->assertViewHas('lowStockCount', 23)->assertViewHas('nearExpiry30Count', 23)->assertViewHas('expiredCount', 23)
            ->assertViewHas('initialAlertTab', 'expired')->assertViewHas('expiredLots', fn ($lots) => $lots->count() === 10)->assertDontSee('Hidden Stock');
        foreach (['low_stock', 'near_expiry', 'expired'] as $category) {
            $this->get(route('inventory.alerts.index', ['category' => $category]))->assertOk()
                ->assertViewHas('records', fn ($records) => $records->total() === 23 && $records->count() === 20)->assertDontSee('Hidden Stock');
            $this->get(route('inventory.alerts.index', ['category' => $category, 'page' => 2]))->assertOk()
                ->assertViewHas('records', fn ($records) => $records->count() === 3)->assertSee('category='.$category, false);
        }
    }

    public function test_alert_empty_invalid_category_and_forbidden_states_are_distinct(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $this->actingAs($viewer)->get(route('inventory.alerts.index'))->assertOk()->assertSee('Tidak ada stok yang cocok');
        $this->getJson(route('inventory.alerts.index', ['category' => 'unknown']))->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson(route('inventory.alerts.index'))->assertForbidden();
    }

    public function test_database_failures_show_recovery_without_fake_empty_results_or_totals(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $this->mock(InventoryDashboardQueryService::class, function ($mock): void {
            $mock->shouldReceive('query')->andThrow(new QueryException('pgsql', 'select inventory alert count', [], new \PDOException('Database unavailable in verification')));
        });
        foreach (['inventory.dashboard' => ['tab' => 'expired'], 'inventory.alerts.index' => ['category' => 'expired']] as $route => $filters) {
            $this->actingAs($viewer)->get(route($route, $filters))->assertStatus(503)
                ->assertSee('Data inventori gagal dimuat')->assertSee('Coba lagi')
                ->assertSee(route($route, $filters), false)->assertDontSee('Semua stok sehat')
                ->assertDontSee('Tidak ada stok yang cocok');
        }
    }
}
