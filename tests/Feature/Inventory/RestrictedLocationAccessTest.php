<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryLocationAccessEvent;
use App\Models\InventoryLocationConfigurationEvent;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\InventoryLocationAccessService;
use App\Services\InventoryMovementService;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RestrictedLocationAccessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsInventoryReservations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $policyFixtureActor = User::factory()->create(['role' => 'penyelia', 'is_active' => false]);
        foreach ([
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ] as $itemType => $lotMode) {
            DB::table('inventory_lot_tracking_policies')->where('item_type', $itemType)->update([
                'lot_tracking_mode' => $lotMode,
                'evidence_reference' => 'TEST-APPROVED-MATRIX',
                'decided_by_user_id' => $policyFixtureActor->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_manager_grant_change_is_audited_and_repeating_it_is_idempotent(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $service = app(InventoryLocationAccessService::class);

        $service->changeGrant($manager, $target->id, $location->id, true, false, 'Penugasan');
        $service->changeGrant($manager, $target->id, $location->id, true, false, 'Penugasan');

        $this->assertDatabaseHas('inventory_location_access', [
            'user_id' => $target->id,
            'location_id' => $location->id,
            'can_view' => true,
            'can_transact' => false,
        ]);
        $this->assertSame(1, InventoryLocationAccessEvent::query()->count());
    }

    public function test_changing_between_view_and_transact_rights_is_audited_as_an_update(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $service = app(InventoryLocationAccessService::class);

        $service->changeGrant($manager, $target->id, $location->id, true, false);
        $service->changeGrant($manager, $target->id, $location->id, false, true);

        $this->assertDatabaseHas('inventory_location_access_events', [
            'target_user_id' => $target->id,
            'location_id' => $location->id,
            'action' => 'updated',
            'previous_can_view' => true,
            'previous_can_transact' => false,
            'can_view' => false,
            'can_transact' => true,
        ]);
    }

    public function test_location_creation_uses_canonical_taxonomy_and_audits_configuration(): void
    {
        $creator = $this->userWithPermission('inventori.create');
        $this->grantRolePermission($creator, 'inventori.location-access.manage');

        $this->actingAs($creator)
            ->post(route('inventory.locations.store'), [
                'name' => 'Cold Room Utama',
                'location_type' => 'cold_storage',
                'is_restricted' => '1',
            ])
            ->assertRedirect(route('inventory.locations.index'))
            ->assertSessionHas('success');

        $location = InventoryLocation::query()->where('name', 'Cold Room Utama')->firstOrFail();
        $event = InventoryLocationConfigurationEvent::query()->where('location_id', $location->id)->firstOrFail();

        $this->assertTrue($location->is_restricted);
        $this->assertSame('created', $event->event_type);
        $this->assertSame($creator->id, $event->actor_user_id);
        $this->assertNull($event->previous_configuration);
        $this->assertSame([
            'name' => 'Cold Room Utama',
            'location_type' => 'cold_storage',
            'is_restricted' => true,
        ], $event->new_configuration);
    }

    public function test_location_creation_rejects_unknown_type_and_missing_permission(): void
    {
        $creator = $this->userWithPermission('inventori.create');

        $this->actingAs($creator)
            ->from(route('inventory.locations.create'))
            ->post(route('inventory.locations.store'), [
                'name' => 'Tipe Tidak Dikenal',
                'location_type' => 'storage',
            ])
            ->assertRedirect(route('inventory.locations.create'))
            ->assertSessionHasErrors('location_type');

        $this->assertDatabaseMissing('inventory_locations', ['name' => 'Tipe Tidak Dikenal']);
        $this->assertDatabaseCount('inventory_location_configuration_events', 0);

        $unauthorized = User::factory()->create(['role' => 'penyelia']);
        $unauthorized->revokePermission('inventori.create');
        $this->actingAs($unauthorized)
            ->post(route('inventory.locations.store'), [
                'name' => 'Unauthorized Location',
                'location_type' => 'lab',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('inventory_locations', ['name' => 'Unauthorized Location']);
        $this->assertDatabaseCount('inventory_location_configuration_events', 0);

        $createOnly = User::factory()->create(['role' => 'penyelia']);
        $this->grantRolePermission($createOnly, 'inventori.create');
        $createOnly->revokePermission('inventori.view');
        $this->actingAs($createOnly)
            ->post(route('inventory.locations.store'), [
                'name' => 'Create-Only Location',
                'location_type' => 'warehouse',
            ])
            ->assertRedirect(route('inventory.locations.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_locations', ['name' => 'Create-Only Location']);
        $this->assertDatabaseHas('inventory_location_configuration_events', [
            'actor_user_id' => $createOnly->id,
            'event_type' => 'created',
        ]);

        $this->actingAs($createOnly)
            ->post(route('inventory.locations.store'), [
                'name' => 'Restricted Without Manager',
                'location_type' => 'warehouse',
                'is_restricted' => '1',
            ])
            ->assertForbidden();
        $this->assertDatabaseMissing('inventory_locations', ['name' => 'Restricted Without Manager']);

        $this->actingAs($createOnly)
            ->from(route('inventory.locations.create'))
            ->post(route('inventory.locations.store'), [
                'name' => 'Invalid Restriction Value',
                'location_type' => 'warehouse',
                'is_restricted' => 'perhaps',
            ])
            ->assertRedirect(route('inventory.locations.create'))
            ->assertSessionHasErrors('is_restricted')
            ->assertSessionHas('errors', fn ($errors): bool => $errors->first('is_restricted') === 'Pilih status akses lokasi yang valid.');
        $this->assertDatabaseMissing('inventory_locations', ['name' => 'Invalid Restriction Value']);
    }

    public function test_postgresql_rejects_unknown_location_types_beyond_controller_validation(): void
    {
        $this->expectException(QueryException::class);
        DB::table('inventory_locations')->insert([
            'name' => 'Unknown Location Type',
            'location_type' => 'storage',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_blocks_raw_location_restriction_toggles(): void
    {
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);

        try {
            DB::transaction(fn () => DB::table('inventory_locations')->where('id', $location->id)->update(['is_restricted' => false]));
            $this->fail('Expected PostgreSQL to reject a raw restricted-status change.');
        } catch (QueryException) {
            $this->assertTrue($location->fresh()->is_restricted);
        }
    }

    public function test_location_taxonomy_migration_fails_closed_on_unknown_legacy_values(): void
    {
        $location = InventoryLocation::factory()->create(['location_type' => 'warehouse']);
        DB::statement('DROP TABLE inventory_location_configuration_events');
        DB::statement('ALTER TABLE inventory_locations DROP CONSTRAINT inventory_locations_location_type_check');
        DB::table('inventory_locations')->where('id', $location->id)->update(['location_type' => 'storage']);

        $migration = require database_path('migrations/2026_10_03_000001_audit_inventory_location_configuration.php');
        try {
            DB::transaction(fn () => $migration->up());
            $this->fail('Expected unknown legacy location types to block taxonomy constraint deployment.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString((string) $location->id, $exception->getMessage());
            $this->assertStringContainsString('storage', $exception->getMessage());
        }

        $this->assertDatabaseHas('inventory_locations', [
            'id' => $location->id,
            'location_type' => 'storage',
        ]);
        $this->assertFalse(Schema::hasTable('inventory_location_configuration_events'));
    }

    public function test_location_update_audits_configuration_and_preserves_restricted_status(): void
    {
        $editor = $this->userWithPermission('inventori.edit');
        $this->grantRolePermission($editor, 'inventori.view');
        $location = InventoryLocation::factory()->create([
            'name' => 'Gudang Lama',
            'location_type' => 'warehouse',
            'is_restricted' => true,
        ]);
        InventoryLocationAccess::query()->create([
            'user_id' => $editor->id,
            'location_id' => $location->id,
            'can_view' => true,
            'can_transact' => false,
        ]);

        $this->actingAs($editor)
            ->put(route('inventory.locations.update', $location), [
                'name' => 'Gudang Reagen',
                'location_type' => 'lab',
            ])
            ->assertRedirect(route('inventory.locations.index'))
            ->assertSessionHas('success');

        $this->assertSame('Gudang Reagen', $location->fresh()->name);
        $this->assertSame('lab', $location->fresh()->location_type);
        $this->assertTrue($location->fresh()->is_restricted);
        $event = InventoryLocationConfigurationEvent::query()->where('location_id', $location->id)->firstOrFail();
        $this->assertSame('updated', $event->event_type);
        $this->assertSame($editor->id, $event->actor_user_id);
        $this->assertSame([
            'name' => 'Gudang Lama',
            'location_type' => 'warehouse',
            'is_restricted' => true,
        ], $event->previous_configuration);
        $this->assertSame([
            'name' => 'Gudang Reagen',
            'location_type' => 'lab',
            'is_restricted' => true,
        ], $event->new_configuration);

        $this->actingAs($editor)
            ->get(route('inventory.locations.edit', $location))
            ->assertOk()
            ->assertSee('Riwayat konfigurasi lokasi')
            ->assertSee('name="is_restricted" value="1" disabled checked', false)
            ->assertSee('Konfigurasi diperbarui')
            ->assertSee('Gudang Lama')
            ->assertSee('Gudang Reagen');

        app(\App\Services\InventoryLocationConfigurationService::class)->update($editor, $location->id, [
            'name' => 'Gudang Reagen',
            'location_type' => 'lab',
            'is_restricted' => false,
        ]);

        $this->assertTrue($location->fresh()->is_restricted);
        $this->assertDatabaseCount('inventory_location_configuration_events', 1);
    }

    public function test_location_configuration_service_requires_both_view_and_edit_permissions(): void
    {
        $editor = $this->userWithPermission('inventori.edit');
        $editor->revokePermission('inventori.view');
        $location = InventoryLocation::factory()->create(['name' => 'View Permission Required']);

        try {
            app(\App\Services\InventoryLocationConfigurationService::class)->update($editor, $location->id, [
                'name' => 'Should Not Update',
                'location_type' => 'lab',
            ]);
            $this->fail('Expected location configuration to require view and edit permissions.');
        } catch (AuthorizationException) {
            $this->assertSame('View Permission Required', $location->fresh()->name);
            $this->assertDatabaseCount('inventory_location_configuration_events', 0);
        }
    }

    public function test_location_configuration_event_failure_rolls_back_create_and_update(): void
    {
        $editor = $this->userWithPermission('inventori.create');
        $this->grantRolePermission($editor, 'inventori.location-access.manage');
        $eventKey = 'eloquent.creating: '.InventoryLocationConfigurationEvent::class;
        $dispatcher = InventoryLocationConfigurationEvent::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (): void {
            throw new RuntimeException('Simulated location configuration audit failure.');
        });

        try {
            app(\App\Services\InventoryLocationConfigurationService::class)->create($editor, [
                'name' => 'Rollback Location',
                'location_type' => 'warehouse',
                'is_restricted' => true,
            ]);
            $this->fail('Expected a failed configuration audit to roll back location creation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated location configuration audit failure.', $exception->getMessage());
            $this->assertDatabaseMissing('inventory_locations', ['name' => 'Rollback Location']);
            $this->assertDatabaseCount('inventory_location_configuration_events', 0);
        } finally {
            $dispatcher->forget($eventKey);
        }

        $location = InventoryLocation::factory()->create(['name' => 'Original Location', 'is_restricted' => false]);
        $editor->grantPermission('inventori.edit');
        $viewerPermission = Permission::query()->where('name', 'inventori.view')->firstOrFail();
        RolePermission::query()->firstOrCreate(['role' => $editor->role, 'permission_id' => $viewerPermission->id]);
        $dispatcher->listen($eventKey, static function (): void {
            throw new RuntimeException('Simulated location configuration audit failure.');
        });

        try {
            app(\App\Services\InventoryLocationConfigurationService::class)->update($editor, $location->id, [
                'name' => 'Updated Location',
                'location_type' => 'lab',
            ]);
            $this->fail('Expected a failed configuration audit to roll back the location update.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated location configuration audit failure.', $exception->getMessage());
            $this->assertSame('Original Location', $location->fresh()->name);
            $this->assertDatabaseCount('inventory_location_configuration_events', 0);
        } finally {
            $dispatcher->forget($eventKey);
        }
    }

    public function test_location_configuration_history_is_append_only(): void
    {
        $creator = $this->userWithPermission('inventori.create');
        $location = app(\App\Services\InventoryLocationConfigurationService::class)->create($creator, [
            'name' => 'Immutable Config Location',
            'location_type' => 'warehouse',
            'is_restricted' => false,
        ]);
        $event = InventoryLocationConfigurationEvent::query()->where('location_id', $location->id)->firstOrFail();

        try {
            DB::transaction(fn () => DB::table('inventory_location_configuration_events')->where('id', $event->id)->update(['event_type' => 'updated']));
            $this->fail('Expected the configuration audit trigger to reject an update.');
        } catch (QueryException) {
            $this->assertSame('created', $event->fresh()->event_type);
        }

        try {
            DB::transaction(fn () => DB::table('inventory_location_configuration_events')->where('id', $event->id)->delete());
            $this->fail('Expected the configuration audit trigger to reject a delete.');
        } catch (QueryException) {
            $this->assertDatabaseHas('inventory_location_configuration_events', ['id' => $event->id]);
        }

        try {
            DB::transaction(fn () => DB::statement('TRUNCATE TABLE inventory_location_configuration_events'));
            $this->fail('Expected the configuration audit trigger to reject a truncate.');
        } catch (QueryException) {
            $this->assertDatabaseHas('inventory_location_configuration_events', ['id' => $event->id]);
        }
    }

    public function test_location_configuration_no_op_does_not_append_and_history_handles_partial_snapshots(): void
    {
        $editor = $this->userWithPermission('inventori.edit');
        $editor->grantPermission('inventori.create');
        $this->grantRolePermission($editor, 'inventori.view');
        $location = app(\App\Services\InventoryLocationConfigurationService::class)->create($editor, [
            'name' => 'Partial Snapshot Location',
            'location_type' => 'warehouse',
            'is_restricted' => false,
        ]);

        app(\App\Services\InventoryLocationConfigurationService::class)->update($editor, $location->id, [
            'name' => $location->name,
            'location_type' => $location->location_type,
        ]);
        $this->assertDatabaseCount('inventory_location_configuration_events', 1);

        InventoryLocationConfigurationEvent::query()->create([
            'location_id' => $location->id,
            'actor_user_id' => $editor->id,
            'event_type' => 'updated',
            'previous_configuration' => [],
            'new_configuration' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($editor)
            ->get(route('inventory.locations.edit', $location))
            ->assertOk()
            ->assertSee('Nama tidak tersedia')
            ->assertSee('Tipe tidak tersedia')
            ->assertSee('Status akses tidak tersedia');
    }

    public function test_location_configuration_history_can_be_paged_past_the_latest_fifty_events(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $editor = $this->userWithPermission('inventori.edit');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $events = [];
        for ($index = 0; $index < 51; $index++) {
            $events[] = [
                'location_id' => $location->id,
                'actor_user_id' => $editor->id,
                'event_type' => 'updated',
                'previous_configuration' => json_encode([
                    'name' => 'Before '.$index,
                    'location_type' => 'warehouse',
                    'is_restricted' => false,
                ]),
                'new_configuration' => json_encode([
                    'name' => 'History Item '.$index,
                    'location_type' => 'lab',
                    'is_restricted' => false,
                ]),
                'occurred_at' => now()->subSeconds($index),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('inventory_location_configuration_events')->insert($events);

        $this->actingAs($viewer)
            ->get(route('inventory.locations.edit', ['location' => $location, 'page' => 2]))
            ->assertOk()
            ->assertSee('History Item 50')
            ->assertSee('page=1');
    }

    public function test_location_access_history_can_be_paged_past_the_latest_hundred_events(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $events = [];
        for ($index = 0; $index < 101; $index++) {
            $events[] = [
                'actor_user_id' => $manager->id,
                'target_user_id' => $target->id,
                'location_id' => $location->id,
                'previous_can_view' => false,
                'previous_can_transact' => false,
                'can_view' => true,
                'can_transact' => false,
                'action' => 'granted',
                'reason' => 'Access history event '.$index,
                'context' => json_encode(['surface' => 'test']),
                'occurred_at' => now()->subSeconds($index),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('inventory_location_access_events')->insert($events);

        $this->actingAs($manager)
            ->get(route('inventory.locations.access', ['location' => $location, 'page' => 2]))
            ->assertOk()
            ->assertSee('Access history event 100')
            ->assertSee('page=1');
    }

    public function test_postgresql_rejects_unknown_location_configuration_event_types(): void
    {
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $actor = User::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('inventory_location_configuration_events')->insert([
            'location_id' => $location->id,
            'actor_user_id' => $actor->id,
            'event_type' => 'deleted',
            'new_configuration' => json_encode([
                'name' => $location->name,
                'location_type' => $location->location_type,
                'is_restricted' => false,
            ]),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_inconsistent_location_configuration_snapshots(): void
    {
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $actor = User::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('inventory_location_configuration_events')->insert([
            'location_id' => $location->id,
            'actor_user_id' => $actor->id,
            'event_type' => 'created',
            'previous_configuration' => json_encode(['name' => 'Invented previous state']),
            'new_configuration' => json_encode([
                'name' => $location->name,
                'location_type' => $location->location_type,
                'is_restricted' => false,
            ]),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_location_access_events_reject_raw_update_and_truncate(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        app(InventoryLocationAccessService::class)->changeGrant($manager, $target->id, $location->id, true, false, 'Terjaga');
        $event = InventoryLocationAccessEvent::query()->firstOrFail();

        try {
            DB::transaction(fn () => DB::table('inventory_location_access_events')->where('id', $event->id)->update(['reason' => 'Diubah']));
            $this->fail('Expected the database append-only trigger to reject an update.');
        } catch (QueryException) {
            $this->assertSame('Terjaga', $event->fresh()->reason);
        }

        try {
            DB::transaction(fn () => DB::statement('TRUNCATE TABLE inventory_location_access_events'));
            $this->fail('Expected the database append-only trigger to reject a truncate.');
        } catch (QueryException) {
            $this->assertSame(1, InventoryLocationAccessEvent::query()->count());
        }
    }

    public function test_manager_without_permission_cannot_read_or_change_grants(): void
    {
        $actor = User::factory()->create(['role' => 'penyelia']);
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);

        $this->actingAs($actor)->get(route('inventory.locations.access', $location))->assertRedirect();
        try {
            app(InventoryLocationAccessService::class)->changeGrant($actor, $target->id, $location->id, true, true);
            $this->fail('Expected missing manager permission to deny the grant.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_location_access', 0);
            $this->assertDatabaseCount('inventory_location_access_events', 0);
        }
    }

    public function test_inactive_access_is_revocation_only_and_impersonated_managers_are_rejected(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $otherManager = $this->userWithPermission('inventori.location-access.manage');
        $inactiveTarget = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $grantService = app(InventoryLocationAccessService::class);
        $grantService->changeGrant($manager, $inactiveTarget->id, $location->id, true, true);
        $inactiveTarget->update(['is_active' => false]);

        $this->actingAs($manager)
            ->from(route('inventory.locations.access', $location))
            ->put(route('inventory.locations.access.update', $location), [
                'user_id' => $inactiveTarget->id,
                'can_view' => '0',
                'reason' => 'Tidak aktif',
            ])
            ->assertRedirect(route('inventory.locations.access', $location));
        $this->assertDatabaseHas('inventory_location_access_events', ['target_user_id' => $inactiveTarget->id, 'action' => 'revoked']);
        $this->assertFalse($grantService->canViewLocation($inactiveTarget, $location));

        try {
            app(InventoryLocationAccessService::class)->changeGrant($otherManager, $manager->id, $location->id, true, false);
            $this->fail('Expected the service to reject an impersonated grant actor.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_location_access', 1);
            $this->assertSame(2, InventoryLocationAccessEvent::count());
        }
    }

    public function test_location_access_screen_and_route_use_the_grant_service(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $manager->revokePermission('inventori.view');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 77,
            'reserved_qty' => 0,
        ]);

        $locationsResponse = $this->actingAs($manager)
            ->get(route('inventory.locations.index'))
            ->assertOk()
            ->assertSee($location->name)
            ->assertSee('Atur akses');
        $locationsResponse->assertViewHas('locations', function ($locations) use ($location): bool {
            $visibleLocation = $locations->getCollection()->firstWhere('id', $location->id);

            return $visibleLocation !== null && (int) $visibleLocation->balances_count === 0;
        })->assertViewHas('visibleLocationIds', fn (array $ids): bool => ! in_array($location->id, $ids, true));

        for ($index = 0; $index < 22; $index++) {
            $hiddenLocation = InventoryLocation::factory()->create([
                'name' => sprintf('Hidden Manager Count %02d', $index),
                'is_restricted' => true,
            ]);
            InventoryBalance::query()->create([
                'item_id' => $item->id,
                'location_id' => $hiddenLocation->id,
                'on_hand_qty' => 77,
                'reserved_qty' => 0,
            ]);
        }

        $this->get(route('inventory.locations.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('locations', fn ($locations): bool => $locations->getCollection()->isNotEmpty()
                && $locations->getCollection()->every(fn ($pageLocation): bool => (int) $pageLocation->balances_count === 0))
            ->assertViewHas('visibleLocationIds', fn (array $ids): bool => $ids === []);

        $accessResponse = $this->actingAs($manager)
            ->get(route('inventory.locations.access', $location));
        $accessResponse
            ->assertOk()
            ->assertSee('href="'.route('inventory.locations.index').'"', false)
            ->assertSee('Lihat riwayat dan laporan')
            ->assertSee('Transaksi (konteks minimum)')
            ->assertSee($target->name);
        preg_match('/<nav[^>]*aria-label="Breadcrumb".*?<\/nav>/s', $accessResponse->getContent(), $breadcrumb);
        $this->assertStringContainsString(route('inventory.locations.index'), $breadcrumb[0] ?? '');
        $this->assertStringNotContainsString('href="'.route('inventory.dashboard').'"', $breadcrumb[0] ?? '');

        $this->put(route('inventory.locations.access.update', $location), [
            'user_id' => $target->id,
            'can_view' => '1',
            'reason' => 'Penugasan pemeriksaan',
        ])->assertRedirect(route('inventory.locations.access', $location));

        $this->assertDatabaseHas('inventory_location_access', [
            'user_id' => $target->id,
            'location_id' => $location->id,
            'can_view' => true,
            'can_transact' => false,
        ]);
        $this->assertDatabaseHas('inventory_location_access_events', [
            'actor_user_id' => $manager->id,
            'target_user_id' => $target->id,
            'location_id' => $location->id,
            'reason' => 'Penugasan pemeriksaan',
        ]);

        $this->get(route('inventory.locations.access', $location))
            ->assertOk()
            ->assertSee('Penugasan pemeriksaan')
            ->assertSee('Lihat tidak menjadi ya');
    }

    public function test_access_form_restores_submitted_rights_and_reason_after_validation_error(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $longReason = str_repeat('R', 2001);

        $this->actingAs($manager)
            ->from(route('inventory.locations.access', $location))
            ->put(route('inventory.locations.access.update', $location), [
                'user_id' => $target->id,
                'can_view' => '1',
                'reason' => $longReason,
            ])
            ->assertRedirect(route('inventory.locations.access', $location))
            ->assertSessionHasErrors('reason');

        $this->actingAs($manager)
            ->get(route('inventory.locations.access', $location))
            ->assertOk()
            ->assertSee('name="can_view" value="1" checked', false)
            ->assertDontSee('name="can_transact" value="1" checked', false)
            ->assertSee('maxlength="2000" value="'.mb_substr($longReason, 0, 2000).'"', false);

        $this->assertDatabaseCount('inventory_location_access', 0);
        $this->assertDatabaseCount('inventory_location_access_events', 0);

        $inactiveTarget = User::factory()->create(['role' => 'penyelia', 'is_active' => false]);
        $this->from(route('inventory.locations.access', $location))
            ->put(route('inventory.locations.access.update', $location), [
                'user_id' => $inactiveTarget->id,
                'can_view' => '1',
            ])
            ->assertRedirect(route('inventory.locations.access', $location))
            ->assertSessionHasErrors('user_id');
        $this->assertDatabaseMissing('inventory_location_access', ['user_id' => $inactiveTarget->id, 'location_id' => $location->id]);
    }

    public function test_location_access_users_are_paginated(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        for ($index = 0; $index < 26; $index++) {
            User::factory()->create([
                'name' => sprintf('Pagination User %02d', $index),
                'role' => 'penyelia',
            ]);
        }

        $this->actingAs($manager)
            ->get(route('inventory.locations.access', ['location' => $location, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('users', fn ($users): bool => $users->currentPage() === 2 && $users->count() === 2)
            ->assertSee('Pagination User');
    }

    public function test_restricted_location_mutation_without_transact_grant_is_denied_before_stock_write(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryMovementService::class)->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 1,
                'uom' => $item->uom,
            ], $actor);
            $this->fail('Expected missing location grant to deny receipt.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
        }
    }

    public function test_transact_only_user_receives_minimum_balance_context(): void
    {
        $actor = $this->userWithPermission('inventori.issue.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 1,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.balance', [
                'context' => 'issue',
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_id' => $location->id,
            ]))
            ->assertOk()
            ->assertExactJson(['on_hand_qty' => 5.0, 'available_qty' => 4.0, 'uom' => $item->uom]);

        $this->getJson(route('inventory.ajax.lots', [
            'item_id' => $item->id,
            'location_id' => $location->id,
        ]))->assertForbidden();
    }

    public function test_transaction_context_requires_the_matching_global_action_permission(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create();
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => false,
            'can_transact' => true,
        ]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.balance', [
                'context' => 'issue',
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_id' => $location->id,
            ]))
            ->assertForbidden();

        $this->getJson(route('inventory.ajax.lots', [
            'context' => 'issue',
            'item_id' => $item->id,
            'lot_mode' => 'untracked',
            'location_id' => $location->id,
        ]))->assertForbidden();
    }

    public function test_transaction_lot_lookup_only_returns_lots_in_the_selected_authorized_location(): void
    {
        $actor = $this->userWithPermission('inventori.issue.create');
        $visibleLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT']);
        $visibleLot = InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'VISIBLE-LOT-91']);
        $hiddenLot = InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'HIDDEN-LOT-91']);
        InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'QUARANTINE-LOT-91', 'status' => 'QUARANTINE']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $visibleLocation->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        $this->grantRolePermission($actor, 'inventori.receipt.create');
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $visibleLot->id,
            'location_id' => $visibleLocation->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $hiddenLot->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.lots', [
                'context' => 'issue',
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'location_id' => $visibleLocation->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.lot_no', 'VISIBLE-LOT-91')
            ->assertJsonMissing(['lot_no' => 'HIDDEN-LOT-91']);

        $this->getJson(route('inventory.ajax.balance', [
            'context' => 'issue',
            'item_id' => $item->id,
            'lot_mode' => 'existing',
            'lot_id' => $hiddenLot->id,
            'location_id' => $visibleLocation->id,
        ]))->assertForbidden();

        $this->getJson(route('inventory.ajax.lots', [
            'context' => 'receipt',
            'item_id' => $item->id,
            'location_id' => $visibleLocation->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.lot_no', 'VISIBLE-LOT-91')
            ->assertJsonMissing(['lot_no' => 'HIDDEN-LOT-91'])
            ->assertJsonMissing(['lot_no' => 'QUARANTINE-LOT-91']);
    }

    public function test_receipt_lot_lookup_includes_lots_with_movement_only_location_history(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create();
        $balancedLot = InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'BALANCED-LOT-92']);
        $movementOnlyLot = InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'MOVEMENT-ONLY-LOT-92']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $balancedLot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 0,
        ]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'lot_id' => $movementOnlyLot->id,
            'to_location_id' => $location->id,
            'qty' => 1,
            'uom' => $item->uom,
            'performed_by' => $actor->id,
            'performed_at' => now(),
        ]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.lots', [
                'context' => 'receipt',
                'item_id' => $item->id,
                'location_id' => $location->id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['lot_no' => 'BALANCED-LOT-92'])
            ->assertJsonFragment(['lot_no' => 'MOVEMENT-ONLY-LOT-92']);
    }

    public function test_receipt_rejects_an_existing_lot_from_another_restricted_location(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $this->grantRolePermission($actor, 'inventori.view');
        $visibleLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT']);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'ACTIVE']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $visibleLocation->id,
            'can_view' => true,
            'can_transact' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->actingAs($actor)
            ->from(route('inventory.transaction.receipt'))
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_id' => $lot->id,
                'lot_mode' => 'existing',
                'location_id' => $visibleLocation->id,
                'qty' => 1,
                'location_mode' => 'existing',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('inventory_movements', [
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'to_location_id' => $visibleLocation->id,
        ]);
        $this->assertDatabaseMissing('inventory_balances', [
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $visibleLocation->id,
        ]);
    }

    public function test_receipt_post_rejects_an_item_with_stock_only_in_an_ungranted_location(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $visibleLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $visibleLocation->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->actingAs($actor)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_mode' => 'existing',
                'location_id' => $visibleLocation->id,
                'qty' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseMissing('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $visibleLocation->id,
        ]);
    }

    public function test_transaction_lot_lookup_rejects_item_ids_with_only_ungranted_stock(): void
    {
        $actor = $this->userWithPermission('inventori.issue.create');
        $authorizedLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenItem = InventoryItem::factory()->create(['item_code' => 'HIDDEN-SELECTOR-ITEM']);
        $hiddenLot = InventoryLot::factory()->create(['item_id' => $hiddenItem->id, 'lot_no' => 'HIDDEN-SELECTOR-LOT']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $authorizedLocation->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $hiddenItem->id,
            'lot_id' => $hiddenLot->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.lots', [
                'context' => 'issue',
                'item_id' => $hiddenItem->id,
                'location_id' => $authorizedLocation->id,
            ]))
            ->assertForbidden();

        $this->getJson(route('inventory.ajax.balance', [
            'context' => 'issue',
            'item_id' => $hiddenItem->id,
            'lot_id' => $hiddenLot->id,
            'location_id' => $authorizedLocation->id,
        ]))->assertForbidden();
    }

    public function test_restricted_issue_succeeds_with_global_permission_and_transact_grant(): void
    {
        $actor = $this->userWithPermission('inventori.issue.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        $movement = app(InventoryMovementService::class)->issue([
            'item_id' => $item->id,
            'lot_mode' => 'untracked',
            'location_id' => $location->id,
            'qty' => 2,
            'uom' => $item->uom,
        ], $actor);

        $this->assertSame('ISSUE', $movement->movement_type);
        $this->assertSame(3.0, (float) InventoryBalance::query()->where('location_id', $location->id)->value('on_hand_qty'));
    }

    public function test_issue_submission_rejects_a_forged_restricted_location_id(): void
    {
        $actor = $this->userWithPermission('inventori.issue.create');
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->actingAs($actor)
            ->post(route('inventory.transaction.issue'), [
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_id' => $hiddenLocation->id,
                'qty' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_transact_grant_allows_receipt_without_granting_stock_history(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        $movement = app(InventoryMovementService::class)->receipt([
            'item_id' => $item->id,
            'lot_mode' => 'untracked',
            'location_id' => $location->id,
            'qty' => 2,
            'uom' => $item->uom,
        ], $actor);

        $this->assertSame('RECEIPT', $movement->movement_type);
        $this->assertFalse(app(InventoryLocationAccessService::class)->canViewLocation($actor, $location));
        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 2,
        ]);
    }

    public function test_receipt_confirmation_does_not_link_to_stock_card_without_view_permission(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $actor->revokePermission('inventori.view');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($actor)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 2,
            ])
            ->assertRedirect(route('inventory.transaction.receipt'))
            ->assertSessionHas('receipt_confirmation', fn (array $confirmation): bool => $confirmation['can_view_stock_card'] === false);

        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertFalse($actor->hasPermission('inventori.view'));
    }

    public function test_transact_grant_does_not_grant_view_or_stock_history(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => false,
            'can_transact' => true,
        ]);

        $access = app(InventoryLocationAccessService::class);
        $this->assertTrue($access->canTransactLocation($actor, $location));
        $this->assertFalse($access->canViewLocation($actor, $location));
        $this->assertFalse($access->visibleLocationIds($actor)->where('id', $location->id)->exists());
    }

    public function test_transfer_requires_transact_grant_for_both_restricted_endpoints(): void
    {
        $actor = $this->userWithPermission('inventori.transfer.create');
        $source = InventoryLocation::factory()->create(['is_restricted' => true]);
        $destination = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create();
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $source->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryMovementService::class)->transfer([
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'from_location_id' => $source->id,
                'to_location_id' => $destination->id,
                'qty' => 1,
                'uom' => $item->uom,
            ], $actor);
            $this->fail('Expected destination grant to be required independently.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
        }
    }

    public function test_transfer_requires_source_transact_grant_even_when_destination_is_authorized(): void
    {
        $actor = $this->userWithPermission('inventori.transfer.create');
        $source = InventoryLocation::factory()->create(['is_restricted' => true]);
        $destination = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $destination->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryMovementService::class)->transfer([
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'from_location_id' => $source->id,
                'to_location_id' => $destination->id,
                'qty' => 1,
            ], $actor);
            $this->fail('Expected source transact grant to be required independently.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
        }
    }

    public function test_transfer_succeeds_when_the_actor_has_both_restricted_location_grants(): void
    {
        $actor = $this->userWithPermission('inventori.transfer.create');
        $source = InventoryLocation::factory()->create(['is_restricted' => true]);
        $destination = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        foreach ([$source, $destination] as $location) {
            InventoryLocationAccess::query()->create([
                'user_id' => $actor->id,
                'location_id' => $location->id,
                'can_view' => false,
                'can_transact' => true,
            ]);
        }
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $source->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->updateOrCreate(
            ['id' => InventoryMutationMode::SINGLETON_ID],
            ['mode' => InventoryMutationMode::ACTIVE],
        );

        $this->actingAs($actor)
            ->post(route('inventory.transaction.transfer'), [
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'from_location_id' => $source->id,
                'to_location_id' => $destination->id,
                'qty' => 2,
                'expected_on_hand_qty' => 5,
                'expected_available_qty' => 5,
            ])
            ->assertRedirect(route('inventory.transaction.transfer'))
            ->assertSessionHas('transfer_confirmation', fn (array $confirmation): bool => $confirmation['source_location_id'] === $source->id
                && $confirmation['destination_location_id'] === $destination->id
                && (float) $confirmation['source_balance'] === 3.0
                && (float) $confirmation['destination_balance'] === 2.0
                && ! $confirmation['can_view_stock_card']);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'TRANSFER',
            'item_id' => $item->id,
            'from_location_id' => $source->id,
            'to_location_id' => $destination->id,
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $source->id,
            'on_hand_qty' => 3,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $destination->id,
            'on_hand_qty' => 2,
        ]);
    }

    public function test_transfer_context_returns_only_authorized_source_and_destination_tuple_balances(): void
    {
        $actor = $this->userWithPermission('inventori.transfer.create');
        $source = InventoryLocation::factory()->create(['is_restricted' => true]);
        $destination = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']);
        foreach ([$source, $destination] as $location) {
            InventoryLocationAccess::query()->create([
                'user_id' => $actor->id,
                'location_id' => $location->id,
                'can_view' => false,
                'can_transact' => true,
            ]);
        }
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'location_id' => $source->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 2,
        ]);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.balance', [
                'context' => 'transfer',
                'balance_endpoint' => 'source',
                'from_location_id' => $source->id,
                'lot_mode' => 'untracked',
                'item_id' => $item->id,
                'location_id' => $source->id,
            ]))
            ->assertOk()
            ->assertExactJson(['on_hand_qty' => 5.0, 'available_qty' => 3.0, 'uom' => 'pcs']);

        $this->getJson(route('inventory.ajax.balance', [
            'context' => 'transfer',
            'balance_endpoint' => 'destination',
            'from_location_id' => $source->id,
            'lot_mode' => 'untracked',
            'item_id' => $item->id,
            'location_id' => $destination->id,
        ]))
            ->assertOk()
            ->assertExactJson(['on_hand_qty' => 0.0, 'available_qty' => 0, 'uom' => 'pcs']);

        $sourceOnlyActor = $this->userWithPermission('inventori.transfer.create');
        InventoryLocationAccess::query()->create([
            'user_id' => $sourceOnlyActor->id,
            'location_id' => $source->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        $this->actingAs($sourceOnlyActor)
            ->getJson(route('inventory.ajax.balance', [
                'context' => 'transfer',
                'balance_endpoint' => 'destination',
                'from_location_id' => $source->id,
                'lot_mode' => 'untracked',
                'item_id' => $item->id,
                'location_id' => $destination->id,
            ]))->assertForbidden();
    }

    public function test_material_disposal_balance_context_requires_the_source_item_grant(): void
    {
        $actor = $this->userWithPermission('inventori.material-disposal.create');
        $source = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $source->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'location_id' => $source->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 2,
        ]);
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.balance', [
                'context' => 'material-disposal',
                'lot_mode' => 'untracked',
                'item_id' => $item->id,
                'location_id' => $source->id,
            ]))
            ->assertOk()
            ->assertExactJson(['on_hand_qty' => 5.0, 'available_qty' => 3.0, 'uom' => 'pcs']);

        $hiddenItem = InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']);
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryBalance::query()->create([
            'item_id' => $hiddenItem->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 9,
            'reserved_qty' => 0,
        ]);

        $this->getJson(route('inventory.ajax.balance', [
            'context' => 'material-disposal',
            'lot_mode' => 'untracked',
            'item_id' => $hiddenItem->id,
            'location_id' => $source->id,
        ]))->assertForbidden();
    }

    public function test_stock_card_denies_transact_only_location_without_view_grant(): void
    {
        $actor = $this->userWithPermission('inventori.view');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => false,
            'can_transact' => true,
        ]);

        $this->actingAs($actor)->get(route('inventory.stock-card', ['location_id' => $location->id]))->assertForbidden();
    }

    public function test_stock_card_rejects_hidden_lot_filter_without_an_item_filter(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenItem = InventoryItem::factory()->create(['item_code' => 'HIDDEN-STOCK-CARD-ITEM']);
        $hiddenLot = InventoryLot::factory()->create(['item_id' => $hiddenItem->id]);
        InventoryBalance::query()->create([
            'item_id' => $hiddenItem->id,
            'lot_id' => $hiddenLot->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($viewer)
            ->get(route('inventory.stock-card', ['lot_id' => $hiddenLot->id]))
            ->assertForbidden();
    }

    public function test_stock_card_resolves_a_visible_lot_filter_without_an_item_filter(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_code' => 'LOT-ONLY-STOCK-CARD']);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'to_location_id' => $location->id,
            'qty' => 4,
            'uom' => $item->uom,
            'performed_by' => $viewer->id,
            'performed_at' => now(),
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($viewer)
            ->get(route('inventory.stock-card', ['lot_id' => $lot->id]))
            ->assertOk()
            ->assertViewHas('selectedItem', fn ($selectedItem): bool => $selectedItem?->is($item) ?? false)
            ->assertViewHas('stockCard', fn (array $rows): bool => count($rows) === 1);
    }

    public function test_stock_card_hides_movements_that_reference_an_unviewable_location(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $visible = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hidden = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create();
        InventoryLocationAccess::query()->create([
            'user_id' => $viewer->id,
            'location_id' => $visible->id,
            'can_view' => true,
            'can_transact' => false,
        ]);
        InventoryMovement::query()->create([
            'movement_type' => 'TRANSFER',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'from_location_id' => $hidden->id,
            'to_location_id' => $visible->id,
            'qty' => 3,
            'uom' => $item->uom,
            'performed_by' => $viewer->id,
            'performed_at' => now(),
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $visible->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($viewer)
            ->get(route('inventory.stock-card', ['item_id' => $item->id, 'location_id' => $visible->id]))
            ->assertOk()
            ->assertDontSee($hidden->name);

        $stockCard = app(InventoryMovementService::class)->getStockCard([
            'item_id' => $item->id,
            'location_id' => $visible->id,
        ], $viewer);
        $this->assertCount(1, $stockCard);
        $this->assertNull($stockCard[0]['movement']->fromLocation);
    }

    public function test_location_edit_cannot_remove_the_restricted_access_boundary(): void
    {
        $editor = $this->userWithPermission('inventori.edit');
        $this->grantRolePermission($editor, 'inventori.view');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryLocationAccess::query()->create([
            'user_id' => $editor->id,
            'location_id' => $location->id,
            'can_view' => true,
            'can_transact' => false,
        ]);

        $this->actingAs($editor)
            ->put(route('inventory.locations.update', $location), [
                'name' => $location->name,
                'location_type' => $location->location_type,
                'is_restricted' => '0',
            ])
            ->assertRedirect(route('inventory.locations.index'));

        $this->assertTrue($location->fresh()->is_restricted);
    }

    public function test_projection_rolls_back_when_audit_event_insert_fails(): void
    {
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $target = User::factory()->create(['role' => 'penyelia']);
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $eventKey = 'eloquent.creating: '.InventoryLocationAccessEvent::class;
        $dispatcher = InventoryLocationAccessEvent::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (): void {
            throw new RuntimeException('Simulated audit insert failure.');
        });

        try {
            app(InventoryLocationAccessService::class)->changeGrant($manager, $target->id, $location->id, true, false);
            $this->fail('Expected the audit event failure to abort the grant transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit insert failure.', $exception->getMessage());
            $this->assertDatabaseCount('inventory_location_access', 0);
            $this->assertDatabaseCount('inventory_location_access_events', 0);
        } finally {
            $dispatcher->forget($eventKey);
        }
    }

    public function test_committed_revocation_denies_the_next_mutation(): void
    {
        $actor = $this->userWithPermission('inventori.receipt.create');
        $manager = $this->userWithPermission('inventori.location-access.manage');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        $item = InventoryItem::factory()->create();
        $access = app(InventoryLocationAccessService::class);
        $access->changeGrant($manager, $actor->id, $location->id, false, true);
        $access->changeGrant($manager, $actor->id, $location->id, false, false, 'Penugasan berakhir');
        InventoryMutationMode::query()->updateOrCreate(['id' => InventoryMutationMode::SINGLETON_ID], ['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryMovementService::class)->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 1,
                'uom' => $item->uom,
            ], $actor);
            $this->fail('Expected a committed revocation to deny the subsequent receipt.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
        }
    }

    public function test_dashboard_aggregates_and_eager_loaded_balances_use_view_grants(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $visible = InventoryLocation::factory()->create(['is_restricted' => true, 'name' => 'Lokasi Terlihat']);
        $hidden = InventoryLocation::factory()->create(['is_restricted' => true, 'name' => 'Lokasi Rahasia']);
        $item = InventoryItem::factory()->create(['name' => 'Item Bersama', 'min_stock' => 10]);
        $hiddenItem = InventoryItem::factory()->create(['name' => 'Item Lokasi Rahasia']);
        $hiddenLot = InventoryLot::factory()->create([
            'item_id' => $hiddenItem->id,
            'lot_no' => 'LOT-RAHASIA-420',
        ]);
        InventoryBalance::query()->create(['item_id' => $item->id, 'location_id' => $visible->id, 'on_hand_qty' => 3, 'reserved_qty' => 0]);
        InventoryBalance::query()->create([
            'item_id' => $hiddenItem->id,
            'lot_id' => $hiddenLot->id,
            'location_id' => $hidden->id,
            'on_hand_qty' => 80,
            'reserved_qty' => 0,
        ]);
        InventoryLocationAccess::query()->create([
            'user_id' => $viewer->id,
            'location_id' => $visible->id,
            'can_view' => true,
            'can_transact' => false,
        ]);

        $response = $this->actingAs($viewer)->getJson(route('inventory.ajax.overview'));
        $itemData = collect($response->json('data'))->firstWhere('id', $item->id);

        $response->assertOk();
        $this->assertSame(3.0, (float) $itemData['total_stock']);
        $this->assertCount(1, $itemData['balances']);
        $this->assertSame('Lokasi Terlihat', $itemData['balances'][0]['location']['name']);
        $this->assertStringNotContainsString('Lokasi Rahasia', $response->getContent());

        $dashboardResponse = $this->get(route('inventory.dashboard'));
        $dashboardResponse->assertOk()->assertViewHas('lowStockItems', function ($items) use ($item): bool {
            $visibleItem = $items->firstWhere('id', $item->id);

            return $visibleItem !== null
                && $visibleItem->total_on_hand === 3.0
                && $visibleItem->balances->count() === 1;
        });

        $searchResponse = $this->getJson(route('inventory.ajax.search', ['q' => 'LOT-RAHASIA-420']));
        $searchResponse->assertOk()
            ->assertJsonPath('exact_match', null)
            ->assertJsonPath('results', []);

        $editor = User::factory()->create(['role' => 'penyelia']);
        $this->grantRolePermission($editor, 'inventori.view');
        $this->grantRolePermission($editor, 'inventori.edit');
        $this->actingAs($editor)
            ->get(route('inventory.items.edit', $hiddenItem))
            ->assertNotFound();
        $this->get(route('inventory.items.lots', $hiddenItem))
            ->assertNotFound();
        $this->get(route('inventory.stock-card', ['item_id' => $hiddenItem->id]))
            ->assertForbidden();
        $this->put(route('inventory.items.update', $hiddenItem), [
            'item_type' => $hiddenItem->item_type,
            'name' => 'Nama yang tidak boleh diubah',
            'uom' => $hiddenItem->uom,
        ])->assertNotFound();
        $this->assertSame('Item Lokasi Rahasia', $hiddenItem->fresh()->name);
    }

    public function test_dashboard_quick_actions_only_receive_transact_authorized_locations(): void
    {
        $actor = $this->userWithPermission('inventori.view');
        $this->grantRolePermission($actor, 'inventori.issue.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => true, 'name' => 'Lokasi Hanya Lihat']);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $location->id,
            'can_view' => true,
            'can_transact' => false,
        ]);

        $this->actingAs($actor)
            ->get(route('inventory.dashboard'))
            ->assertOk()
            ->assertDontSee('Lokasi Hanya Lihat');
    }

    public function test_dashboard_counts_all_visible_low_stock_items_and_excludes_restricted_only_items(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $visibleLowStockItems = collect(range(1, 11))
            ->map(fn (int $number) => InventoryItem::factory()->create([
                'name' => 'Visible Low Stock '.$number,
                'min_stock' => 5,
                'is_active' => true,
            ]));
        $restrictedItem = InventoryItem::factory()->create([
            'name' => 'Restricted Low Stock Item',
            'min_stock' => 5,
            'is_active' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $restrictedItem->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($viewer)
            ->get(route('inventory.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['low_stock'] === 11)
            ->assertViewHas('lowStockItems', function ($items) use ($visibleLowStockItems, $restrictedItem): bool {
                return $items->count() === 10
                    && $visibleLowStockItems->contains(fn (InventoryItem $item): bool => $items->contains('id', $item->id))
                    && ! $items->contains('id', $restrictedItem->id);
            });
    }

    public function test_global_search_does_not_reveal_an_orphaned_lot_for_a_hidden_item(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $hiddenItem = InventoryItem::factory()->create([
            'name' => 'Item dengan riwayat terbatas',
            'item_code' => 'RESTRICTED-ITEM-LOT',
        ]);
        $orphanedLot = InventoryLot::factory()->create([
            'item_id' => $hiddenItem->id,
            'lot_no' => 'RESTRICTED-ORPHAN-LOT',
        ]);
        InventoryBalance::query()->create([
            'item_id' => $hiddenItem->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($viewer)
            ->getJson(route('inventory.ajax.search', ['q' => $orphanedLot->lot_no]))
            ->assertOk()
            ->assertJsonPath('exact_match', null)
            ->assertJsonPath('results', []);

        $this->get(route('inventory.stock-card', ['item_id' => $hiddenItem->id]))
            ->assertForbidden();
    }

    public function test_dashboard_expiry_badges_count_all_visible_lots_beyond_the_preview_limit(): void
    {
        $viewer = $this->userWithPermission('inventori.view');
        $visibleLocation = InventoryLocation::factory()->create(['is_restricted' => false]);
        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);

        foreach (range(1, 11) as $number) {
            $nearItem = InventoryItem::factory()->create(['name' => 'Near Reagent '.$number]);
            $nearLot = InventoryLot::factory()->create([
                'item_id' => $nearItem->id,
                'lot_no' => 'NEAR-'.$number,
                'expiry_date' => Carbon::today()->addDays(15),
                'status' => 'ACTIVE',
            ]);
            InventoryBalance::query()->create([
                'item_id' => $nearItem->id,
                'lot_id' => $nearLot->id,
                'location_id' => $visibleLocation->id,
                'on_hand_qty' => 1,
                'reserved_qty' => 0,
            ]);

            $expiredItem = InventoryItem::factory()->create(['name' => 'Expired Reagent '.$number]);
            $expiredLot = InventoryLot::factory()->create([
                'item_id' => $expiredItem->id,
                'lot_no' => 'EXPIRED-'.$number,
                'expiry_date' => Carbon::yesterday(),
                'status' => 'EXPIRED',
            ]);
            InventoryBalance::query()->create([
                'item_id' => $expiredItem->id,
                'lot_id' => $expiredLot->id,
                'location_id' => $visibleLocation->id,
                'on_hand_qty' => 1,
                'reserved_qty' => 0,
            ]);
        }

        $hiddenItem = InventoryItem::factory()->create(['name' => 'Hidden Expired Reagent']);
        $hiddenLot = InventoryLot::factory()->create([
            'item_id' => $hiddenItem->id,
            'lot_no' => 'HIDDEN-EXPIRED',
            'expiry_date' => Carbon::yesterday(),
            'status' => 'EXPIRED',
        ]);
        InventoryBalance::query()->create([
            'item_id' => $hiddenItem->id,
            'lot_id' => $hiddenLot->id,
            'location_id' => $hiddenLocation->id,
            'on_hand_qty' => 7,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($viewer)
            ->get(route('inventory.dashboard'))
            ->assertOk()
            ->assertViewHas('nearExpiry30Count', 11)
            ->assertViewHas('nearExpiry30', fn ($lots): bool => $lots->count() === 10)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['expired'] === 11)
            ->assertViewHas('expiredLots', fn ($lots): bool => $lots->count() === 10)
            ->assertDontSee('Hidden Expired Reagent')
            ->assertSee('11');
    }

    private function userWithPermission(string $permissionName): User
    {
        $user = User::factory()->create(['role' => 'penyelia']);
        $permission = Permission::query()->where('name', $permissionName)->firstOrFail();
        RolePermission::query()->firstOrCreate(['role' => $user->role, 'permission_id' => $permission->id]);

        return $user;
    }

    private function grantRolePermission(User $user, string $permissionName): void
    {
        $permission = Permission::query()->where('name', $permissionName)->firstOrFail();
        RolePermission::query()->firstOrCreate(['role' => $user->role, 'permission_id' => $permission->id]);
    }
}
