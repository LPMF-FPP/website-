<?php

namespace Tests\Feature\WhatsApp;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\WhatsApp\Commands\StockTransactionCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransactionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_stok_masuk_creates_inventory_movement_and_updates_balance(): void
    {
        InventoryLocation::factory()->create();

        $item = InventoryItem::factory()->create([
            'name' => 'Alkohol 96%',
            'uom' => 'Liter',
            'is_active' => true,
        ]);

        /** @var StockTransactionCommand $command */
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628123456789@s.whatsapp.net', ['masuk', 'alkohol', '5']);
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

    public function test_stok_keluar_creates_inventory_movement_and_updates_balance(): void
    {
        $location = InventoryLocation::factory()->create();

        $item = InventoryItem::factory()->create([
            'name' => 'Alkohol 96%',
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

        $result = $command->execute('628123456789@s.whatsapp.net', ['keluar', 'alkohol', '2']);
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
        InventoryLocation::factory()->create();

        $user = User::factory()->create([
            'phone' => '08123456789',
        ]);

        InventoryItem::factory()->create([
            'name' => 'Masker',
            'uom' => 'pcs',
            'is_active' => true,
        ]);

        /** @var StockTransactionCommand $command */
        $command = app(StockTransactionCommand::class);

        $result = $command->execute('628123456789@s.whatsapp.net', ['masuk', 'masker', '3']);
        $this->assertStringStartsWith('✅', $result, $result);

        $movement = InventoryMovement::where('movement_type', 'RECEIPT')->latest('id')->firstOrFail();
        $this->assertEquals($user->id, $movement->performed_by);
    }
}
