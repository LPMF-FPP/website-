<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryItemLifecycleEvent;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\InventoryItemLifecycleService;
use App\Services\InventoryMovementService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InventoryItemArchivalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_archive_and_restore_are_audited_and_preserve_the_item_record(): void
    {
        $editor = $this->userWithPermissions(['inventori.view', 'inventori.edit', 'inventori.delete']);
        $item = InventoryItem::factory()->create(['item_code' => 'ARCHIVE-01']);

        $this->actingAs($editor)
            ->post(route('inventory.items.archive', $item), ['reason' => 'Item tidak lagi digunakan'])
            ->assertRedirect(route('inventory.items.index', ['status' => 'inactive']));

        $this->assertFalse($item->fresh()->is_active);
        $this->assertDatabaseHas('inventory_item_lifecycle_events', [
            'inventory_item_id' => $item->id,
            'actor_user_id' => $editor->id,
            'previous_is_active' => true,
            'is_active' => false,
            'event_type' => 'archived',
            'reason' => 'Item tidak lagi digunakan',
        ]);

        $this->put(route('inventory.items.update', $item), [
            ...$this->itemUpdatePayload($item),
            'is_active' => '1',
        ])->assertRedirect(route('inventory.items.index'));
        $this->assertFalse($item->fresh()->is_active);

        $this->post(route('inventory.items.restore', $item), ['reason' => 'Kebutuhan operasional kembali'])
            ->assertRedirect(route('inventory.items.index'));

        $this->assertTrue($item->fresh()->is_active);
        $this->assertDatabaseCount('inventory_item_lifecycle_events', 2);
        $this->assertDatabaseHas('inventory_item_lifecycle_events', [
            'inventory_item_id' => $item->id,
            'actor_user_id' => $editor->id,
            'previous_is_active' => false,
            'is_active' => true,
            'event_type' => 'restored',
            'reason' => 'Kebutuhan operasional kembali',
        ]);

        $this->delete(route('inventory.items.destroy', $item))
            ->assertRedirect(route('inventory.items.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
        $this->assertDatabaseCount('inventory_item_lifecycle_events', 2);
    }

    public function test_archival_is_denied_to_a_user_without_item_edit_permission(): void
    {
        $viewer = $this->userWithPermissions(['inventori.view']);
        $viewer->revokePermission('inventori.edit');
        $item = InventoryItem::factory()->create();

        $this->actingAs($viewer)
            ->post(route('inventory.items.archive', $item))
            ->assertRedirect();

        $this->assertTrue($item->fresh()->is_active);
        $this->assertDatabaseCount('inventory_item_lifecycle_events', 0);
    }

    public function test_archival_blocks_positive_stock_and_does_not_write_an_event(): void
    {
        $editor = $this->userWithPermissions(['inventori.view', 'inventori.edit']);
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($editor)
            ->from(route('inventory.items.index'))
            ->post(route('inventory.items.archive', $item))
            ->assertRedirect(route('inventory.items.index'))
            ->assertSessionHasErrors('archive');

        $this->assertTrue($item->fresh()->is_active);
        $this->assertDatabaseCount('inventory_item_lifecycle_events', 0);
    }

    public function test_archival_does_not_reveal_hidden_positive_stock(): void
    {
        $editor = $this->userWithPermissions(['inventori.view', 'inventori.edit']);
        $visibleLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_code' => 'HIDDEN-STOCK-01']);
        InventoryLocationAccess::query()->create([
            'user_id' => $editor->id,
            'location_id' => $visibleLocation->id,
            'can_view' => true,
            'can_transact' => false,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $visibleLocation->id,
            'on_hand_qty' => 0,
            'reserved_qty' => 0,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 0,
        ]);

        $response = $this->actingAs($editor)->post(route('inventory.items.archive', $item));

        $response->assertForbidden();
        $this->assertTrue($item->fresh()->is_active);
        $this->assertDatabaseCount('inventory_item_lifecycle_events', 0);
    }

    public function test_archive_and_restore_fail_atomically_when_event_insert_fails(): void
    {
        $editor = $this->userWithPermissions(['inventori.view', 'inventori.edit']);
        $item = InventoryItem::factory()->create();
        $eventKey = 'eloquent.creating: '.InventoryItemLifecycleEvent::class;
        $dispatcher = InventoryItemLifecycleEvent::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (): void {
            throw new RuntimeException('Simulated lifecycle event failure.');
        });

        try {
            app(InventoryItemLifecycleService::class)->archive($item->id, $editor, 'Test rollback');
            $this->fail('Expected an audit failure to roll back the archive state.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated lifecycle event failure.', $exception->getMessage());
            $this->assertTrue($item->fresh()->is_active);
            $this->assertDatabaseCount('inventory_item_lifecycle_events', 0);
        } finally {
            $dispatcher->forget($eventKey);
        }
    }

    public function test_historical_item_deletion_is_denied_by_route_model_and_raw_bulk_path(): void
    {
        $operator = $this->userWithPermissions(['inventori.view', 'inventori.delete']);
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id]);
        $balance = InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
        ]);
        $movement = InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'to_location_id' => $location->id,
            'qty' => 1,
            'uom' => $item->uom,
            'performed_by' => $operator->id,
            'performed_at' => now(),
        ]);

        $this->actingAs($operator)
            ->from(route('inventory.items.index'))
            ->delete(route('inventory.items.destroy', $item))
            ->assertRedirect(route('inventory.items.index'))
            ->assertSessionHas('error');

        try {
            $item->fresh()->delete();
            $this->fail('Expected the model guard to deny deletion of an item with history.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
        }

        try {
            DB::transaction(fn () => DB::table('inventory_items')->where('id', $item->id)->delete());
            $this->fail('Expected the database trigger to deny a raw bulk delete with history.');
        } catch (QueryException) {
            $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
        }

        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->id]);
        $this->assertDatabaseHas('inventory_balances', ['id' => $balance->id]);
        $this->assertDatabaseHas('inventory_movements', ['id' => $movement->id]);
    }

    public function test_history_free_item_can_be_permanently_deleted_with_delete_permission(): void
    {
        $operator = $this->userWithPermissions(['inventori.view', 'inventori.delete']);
        $item = InventoryItem::factory()->create();

        $this->actingAs($operator)
            ->delete(route('inventory.items.destroy', $item))
            ->assertRedirect(route('inventory.items.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    public function test_archived_item_is_hidden_from_operations_but_remains_in_historical_views(): void
    {
        $operator = $this->userWithPermissions(['inventori.view', 'inventori.edit', 'inventori.issue.create']);
        $item = InventoryItem::factory()->create(['item_code' => 'ARCHIVED-HISTORY-01']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 0,
            'reserved_qty' => 0,
        ]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 1,
            'uom' => $item->uom,
            'performed_by' => $operator->id,
            'performed_at' => now()->subDay(),
        ]);

        app(InventoryItemLifecycleService::class)->archive($item->id, $operator, 'Tidak digunakan lagi');
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->actingAs($operator)
            ->get(route('inventory.transaction.issue'))
            ->assertOk()
            ->assertDontSee('ARCHIVED-HISTORY-01');

        $this->get(route('inventory.stock-card', [
            'item_id' => $item->id,
            'location_id' => $location->id,
        ]))
            ->assertOk()
            ->assertSee('ARCHIVED-HISTORY-01');

        try {
            app(InventoryMovementService::class)->issue([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 1,
                'uom' => $item->uom,
            ], $operator);
            $this->fail('Expected the movement service to reject an archived item.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('diarsipkan', $exception->getMessage());
            $this->assertDatabaseCount('inventory_movements', 1);
        }
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create(['role' => 'penyelia']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->where('name', $permissionName)->firstOrFail();
            RolePermission::query()->firstOrCreate([
                'role' => $user->role,
                'permission_id' => $permission->id,
            ]);
        }

        return $user;
    }

    private function itemUpdatePayload(InventoryItem $item): array
    {
        return [
            'item_code' => $item->item_code,
            'item_type' => $item->item_type,
            'name' => $item->name,
            'brand' => $item->brand,
            'manufacturer' => $item->manufacturer,
            'specification' => $item->specification,
            'uom' => $item->uom,
            'pack_size' => $item->pack_size,
            'min_stock' => $item->min_stock,
        ];
    }
}
