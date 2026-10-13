<?php

namespace Tests\Feature\WhatsApp;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\User;
use App\Services\InventoryLotTrackingPolicyService;
use App\Services\WhatsApp\Commands\StockTransactionCommand;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransactionCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_stok_masuk_creates_inventory_movement_and_updates_balance(): void
    {
        InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->authorizeStockCommand();

        $item = InventoryItem::factory()->create([
            'name' => 'Alkohol 96%',
            'item_type' => 'CONSUMABLE',
            'uom' => 'Liter',
            'is_active' => true,
        ]);

        /** @var StockTransactionCommand $command */
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628123456789@s.whatsapp.net', ['masuk', 'untracked', 'alkohol', '5']);
        $this->assertStringStartsWith('✅', $result, $result);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RECEIPT',
            'item_id' => $item->id,
            'qty' => 5,
            'uom' => 'Liter',
        ]);

        $movement = InventoryMovement::where('movement_type', 'RECEIPT')->firstOrFail();
        $this->assertNotNull($movement->to_location_id);

        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $movement->to_location_id,
        ]);

        $balance = InventoryBalance::where([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $movement->to_location_id,
        ])->firstOrFail();

        $this->assertEquals(5.0, (float) $balance->on_hand_qty);
    }

    public function test_receipt_requires_an_explicit_untracked_mode_in_the_whatsapp_command(): void
    {
        InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->authorizeStockCommand();
        InventoryItem::factory()->create([
            'name' => 'Alkohol 96%',
            'item_type' => 'CONSUMABLE',
            'uom' => 'Liter',
            'is_active' => true,
        ]);
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628123456789@s.whatsapp.net', ['masuk', 'alkohol', '5']);

        $this->assertStringContainsString('untracked', $result);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_lot_tracked_whatsapp_receipt_is_directed_to_the_explicit_web_form(): void
    {
        InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->authorizeStockCommand();
        InventoryItem::factory()->create([
            'name' => 'Reagen Lot Diperlukan',
            'item_type' => 'REAGENT',
            'uom' => 'g',
            'is_active' => true,
        ]);
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628123456789@s.whatsapp.net', ['masuk', 'untracked', 'reagen', '5']);

        $this->assertStringContainsString('formulir Penerimaan Stok', $result);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_stok_keluar_creates_inventory_movement_and_updates_balance(): void
    {
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->authorizeStockCommand();

        $item = InventoryItem::factory()->create([
            'name' => 'Alkohol 96%',
            'item_type' => 'CONSUMABLE',
            'uom' => 'Liter',
            'is_active' => true,
        ]);

        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 10,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);

        /** @var StockTransactionCommand $command */
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628123456789@s.whatsapp.net', ['keluar', 'untracked', 'alkohol', '2']);
        $this->assertStringStartsWith('✅', $result, $result);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ISSUE',
            'item_id' => $item->id,
            'qty' => 2,
            'uom' => 'Liter',
            'from_location_id' => $location->id,
        ]);

        $balance = InventoryBalance::where([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
        ])->firstOrFail();

        $this->assertEquals(8.0, (float) $balance->on_hand_qty);
    }

    public function test_stok_sets_performed_by_when_user_phone_matches_from_jid(): void
    {
        InventoryLocation::factory()->create(['is_restricted' => false]);

        $user = $this->authorizeStockCommand();

        InventoryItem::factory()->create([
            'name' => 'Masker',
            'item_type' => 'CONSUMABLE',
            'uom' => 'pcs',
            'is_active' => true,
        ]);

        /** @var StockTransactionCommand $command */
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628123456789@s.whatsapp.net', ['masuk', 'untracked', 'masker', '3']);
        $this->assertStringStartsWith('✅', $result, $result);

        $movement = InventoryMovement::where('movement_type', 'RECEIPT')->latest('id')->firstOrFail();
        $this->assertEquals($user->id, $movement->performed_by);
    }

    public function test_stok_transaction_without_an_authorized_application_user_is_rejected(): void
    {
        InventoryLocation::factory()->create();
        InventoryItem::factory()->create([
            'name' => 'Masker',
            'uom' => 'pcs',
            'is_active' => true,
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        /** @var StockTransactionCommand $command */
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628199999999@s.whatsapp.net', ['masuk', 'untracked', 'masker', '3']);

        $this->assertStringStartsWith('⚠️', $result);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    private function authorizeStockCommand(): User
    {
        $user = User::factory()->create([
            'phone' => '08123456789',
        ]);
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.issue.create');
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.location-access.manage');
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($user, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ], 'Kebijakan lot fixture WhatsApp');

        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        return $user;
    }
}
