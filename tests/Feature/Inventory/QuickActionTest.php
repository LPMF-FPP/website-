<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMutationMode;
use App\Models\User;
use App\Services\InventoryLotTrackingPolicyService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_quick_issue_submission()
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = User::factory()->create();
        $user->grantPermission('inventori.issue.create');

        $policyManager = User::factory()->create(['role' => 'penyelia']);
        $policyManager->grantPermission('inventori.view');
        $policyManager->grantPermission('inventori.location-access.manage');
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($policyManager, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
        ], 'Kebijakan lot fixture quick issue');
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $this->actingAs($user);
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'pcs']);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id]);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);

        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'lot_mode' => 'existing',
            'location_id' => $location->id,
            'on_hand_qty' => 100,
        ]);

        $response = $this->post(route('inventory.transaction.issue'), [
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'qty' => 5,
            'lot_mode' => 'existing',
            'reference_type' => 'MANUAL',
            'notes' => 'Quick issue from dashboard',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('inventory_movements', [
            'item_id' => $item->id,
            'qty' => 5,
            'movement_type' => 'ISSUE',
            'notes' => 'Quick issue from dashboard',
        ]);
    }
}
