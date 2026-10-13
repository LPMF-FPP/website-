<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\ActivityLog;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\InventoryMovementService;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryMutationAuthorizationTest extends TestCase
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

    public function test_inventory_permissions_are_distinct_and_not_assigned_by_default(): void
    {
        $permissionNames = [
            'inventori.receipt.create',
            'inventori.issue.create',
            'inventori.transfer.create',
            'inventori.stocktake.submit',
            'inventori.stocktake.approve',
            'inventori.material-disposal.create',
            'inventori.location-access.manage',
        ];
        $permissionIds = Permission::query()->whereIn('name', $permissionNames)->pluck('id');

        $this->assertCount(count($permissionNames), $permissionIds);
        $this->assertSame(0, RolePermission::query()->whereIn('permission_id', $permissionIds)->count());

        $user = User::factory()->create();
        $permissionData = app(PermissionService::class)->getPermissionsForUI($user);

        foreach (['receipt.create', 'issue.create', 'transfer.create', 'stocktake.submit', 'stocktake.approve', 'material-disposal.create', 'location-access.manage'] as $action) {
            $this->assertArrayHasKey($action, $permissionData['inventori']['actions']);
            $this->assertFalse($permissionData['inventori']['actions'][$action]['has_access']);
        }
    }

    public function test_per_user_permission_panel_renders_the_action_specific_inventory_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('analysts.show', $target));

        $response->assertOk();
        $response->assertSee('Catat Penerimaan');
        $response->assertSee('Catat Pengeluaran');
        $response->assertSee('Catat Transfer');
        $response->assertSee('Ajukan Stok Opname');
        $response->assertSee('Setujui Selisih Stok Opname');
        $response->assertSee('Catat Disposal Bahan');
        $response->assertSee('Kelola Akses Lokasi');
        $receiptPermission = Permission::query()->where('name', 'inventori.receipt.create')->firstOrFail();
        $response->assertSee('name="permissions['.$receiptPermission->id.']"', false);
    }

    public function test_per_user_permission_panel_keeps_self_permission_controls_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $permission = Permission::query()->where('name', 'inventori.receipt.create')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('analysts.show', $admin));
        $response->assertOk();

        preg_match(
            '/<input type="checkbox"[^>]*name="permissions\['.$permission->id.'\]"[^>]*>/s',
            $response->getContent(),
            $matches
        );

        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('disabled', $matches[0]);
    }

    public function test_permission_form_string_zero_reliably_revokes_a_role_default(): void
    {
        $user = User::factory()->create(['role' => 'penyelia']);
        $permission = Permission::query()->where('name', 'inventori.view')->firstOrFail();

        $this->assertTrue($user->hasPermission('inventori.view'));

        app(PermissionService::class)->syncUserPermissions(
            $user,
            [$permission->id => '0'],
            User::factory()->create(['role' => 'admin'])
        );

        $this->assertFalse($user->hasPermission('inventori.view'));
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => false,
        ]);
    }

    public function test_permission_management_persists_and_audits_a_checkbox_revocation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'penyelia']);
        $permission = Permission::query()->where('name', 'inventori.view')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('analysts.permissions.update', $target), [
                'permissions' => [$permission->id => '0'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $target->id,
            'permission_id' => $permission->id,
            'granted' => false,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'action' => 'USER_PERMISSIONS_UPDATED',
        ]);
    }

    public function test_permission_management_can_grant_a_new_action_permission_to_an_individual(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'analis']);
        $permission = Permission::query()->where('name', 'inventori.receipt.create')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('analysts.permissions.update', $target), [
                'permissions' => [$permission->id => '1'],
            ])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasPermission('inventori.receipt.create'));
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $target->id,
            'permission_id' => $permission->id,
            'granted' => true,
        ]);
    }

    public function test_permission_management_rejects_unknown_permission_ids(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'analis']);

        $this->actingAs($admin)
            ->put(route('analysts.permissions.update', $target), [
                'permissions' => [99999999 => '1'],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseCount('user_permissions', 0);
    }

    public function test_permission_service_rejects_malformed_ids_and_grant_values(): void
    {
        $user = User::factory()->create();
        $permissionId = Permission::query()->where('name', 'inventori.view')->value('id');

        try {
            app(PermissionService::class)->syncUserPermissions($user, ['not-an-id' => true]);
            $this->fail('Malformed permission identifiers must be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('user_permissions', 0);
        }

        try {
            app(PermissionService::class)->syncUserPermissions($user, [$permissionId => '0 ']);
            $this->fail('Malformed permission grant values must be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('user_permissions', 0);
        }
    }

    public function test_reset_permission_management_removes_user_overrides_and_restores_role_defaults(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'analis']);
        $target->revokePermission('inventori.view');
        $this->assertFalse($target->hasPermission('inventori.view'));

        $this->actingAs($admin)
            ->post(route('analysts.permissions.reset', $target))
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasPermission('inventori.view'));
        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $target->id,
            'permission_id' => Permission::query()->where('name', 'inventori.view')->value('id'),
        ]);
    }

    public function test_staff_profile_role_change_resets_custom_inventory_action_overrides(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'penyelia']);
        $target->grantPermission('inventori.receipt.create');
        $permission = Permission::query()->where('name', 'inventori.receipt.create')->firstOrFail();
        $this->assertTrue($target->hasPermission('inventori.receipt.create'));

        $this->actingAs($admin)
            ->patch(route('analysts.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'role' => 'analis',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'analis']);
        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $target->id,
            'permission_id' => $permission->id,
        ]);
        $this->assertFalse($target->fresh()->hasPermission('inventori.receipt.create'));
    }

    public function test_dashboard_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $denied = $this->from(route('testing.index'))
            ->actingAs($user)
            ->get(route('inventory.dashboard'));

        $denied->assertRedirect(route('testing.index'));
        $denied->assertSessionHas('error');

        $user->grantPermission('inventori.view');

        $this->actingAs($user)
            ->get(route('inventory.dashboard'))
            ->assertOk();
    }

    public function test_unauthorized_json_read_returns_403_without_inventory_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('inventory.ajax.search', ['q' => 'restricted-item']));

        $response->assertForbidden();
        $response->assertJsonMissingPath('results');
    }

    public function test_stock_balance_ajax_requires_an_inventory_action_permission(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);

        $this->actingAs($user)
            ->getJson(route('inventory.ajax.balance', [
                'context' => 'issue',
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_id' => $location->id,
            ]))
            ->assertForbidden();
    }

    public function test_stock_card_pdf_requires_inventory_view_permission(): void
    {
        $user = User::factory()->create();

        $this->from(route('testing.index'))
            ->actingAs($user)
            ->get(route('inventory.stock-card.print'))
            ->assertRedirect(route('testing.index'))
            ->assertSessionHas('error');
    }

    public function test_unverified_user_is_redirected_before_inventory_permission_check(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('inventory.dashboard'))
            ->assertRedirect();
    }

    public function test_receipt_form_requires_action_permission_without_granting_dashboard_view(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');

        $this->from(route('testing.index'))
            ->actingAs($user)
            ->get(route('inventory.transaction.receipt'))
            ->assertRedirect(route('testing.index'))
            ->assertSessionHas('error');

        $user->grantPermission('inventori.receipt.create');
        $user->revokePermission('inventori.view');

        $this->actingAs($user)
            ->get(route('inventory.transaction.receipt'))
            ->assertOk();
    }

    public function test_transaction_stock_context_allows_the_action_permission_without_global_history_view(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.issue.create');
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->getJson(route('inventory.ajax.balance', [
                'context' => 'issue',
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_id' => $location->id,
            ]))
            ->assertOk();
    }

    public function test_direct_item_write_route_denies_before_controller_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('testing.index'))
            ->actingAs($user)
            ->post(route('inventory.items.store'), []);

        $response->assertRedirect(route('testing.index'));
        $response->assertSessionHas('error');
        $response->assertSessionMissing('_old_input');
    }

    public function test_delete_permission_cannot_remove_an_item_with_ledger_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $movement = InventoryMovement::factory()->create([
            'item_id' => $item->id,
            'lot_id' => null,
            'from_location_id' => $location->id,
            'to_location_id' => null,
            'movement_type' => 'ISSUE',
        ]);

        $this->actingAs($admin)
            ->delete(route('inventory.items.destroy', $item))
            ->assertRedirect(route('inventory.items.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
        $this->assertDatabaseHas('inventory_movements', ['id' => $movement->id]);
    }

    public function test_admin_with_action_permission_is_denied_while_default_gate_is_disabled(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->grantPermission('inventori.receipt.create');
        $this->actingAs($user);

        [$item, $location] = $this->createStockContext();

        try {
            app(InventoryMovementService::class)->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 5,
                'uom' => $item->uom,
                'performed_by' => $user->id,
            ]);
            $this->fail('Expected the disabled mutation gate to deny the receipt.');
        } catch (AuthorizationException) {
            $this->assertSame(InventoryMutationMode::DISABLED, InventoryMutationMode::query()->findOrFail(1)->mode);
        }

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_every_stock_mutation_service_entry_point_is_blocked_while_gate_is_disabled(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $service = app(InventoryMovementService::class);

        $operations = [
            fn () => $service->receipt(['performed_by' => $user->id], $user),
            fn () => $service->issue(['performed_by' => $user->id], $user),
            fn () => $service->transfer(['performed_by' => $user->id], $user),
            fn () => $service->adjust(['performed_by' => $user->id], $user),
            fn () => $service->dispose(['performed_by' => $user->id], $user),
            fn () => $service->processStocktake([], $user->id, $user),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Expected every mutation service entry point to honor the disabled gate.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('inventory_movements', 0);
            }
        }

        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_transaction_and_disposal_services_require_their_action_permissions(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $service = app(InventoryMovementService::class);

        $operations = [
            fn () => $service->receipt(['performed_by' => $user->id], $user),
            fn () => $service->issue(['performed_by' => $user->id], $user),
            fn () => $service->transfer(['performed_by' => $user->id], $user),
            fn () => $service->dispose(['performed_by' => $user->id], $user),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('A read permission must not authorize a stock mutation.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('inventory_movements', 0);
            }
        }

        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_adjustment_and_legacy_stocktake_paths_cannot_post_unreviewed_variances(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.stocktake.submit');
        [$item, $location] = $this->createStockContext();
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $service = app(InventoryMovementService::class);

        try {
            $service->adjust([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 2,
                'uom' => $item->uom,
                'performed_by' => $user->id,
            ], $user);
            $this->fail('Direct adjustments require an approved stocktake revision.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        try {
            $service->processStocktake([[
                'item_id' => $item->id,
                'location_id' => $location->id,
                'counted_qty' => 2,
            ]], $user->id, $user);
            $this->fail('The legacy stocktake path must not post an unreviewed adjustment.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_missing_or_unknown_gate_state_denies_service_mutations_without_writes(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $this->actingAs($user);
        [$item, $location] = $this->createStockContext();

        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => 'unknown']);
        $this->assertReceiptDenied($user, $item, $location);

        InventoryMutationMode::query()->whereKey(1)->delete();
        $this->assertReceiptDenied($user, $item, $location);

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_unreadable_gate_state_fails_closed_without_writes(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        [$item, $location] = $this->createStockContext();
        Schema::drop('inventory_mutation_modes');

        $this->assertReceiptDenied($user, $item, $location);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_direct_service_call_requires_actor_and_action_permission_even_when_gate_is_active(): void
    {
        [$item, $location] = $this->createStockContext();
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryMovementService::class)->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 2,
                'uom' => $item->uom,
            ]);
            $this->fail('Expected a direct call without an actor to be denied.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        $user = User::factory()->create();
        try {
            app(InventoryMovementService::class)->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 2,
                'uom' => $item->uom,
                'performed_by' => $user->id,
            ], $user);
            $this->fail('Expected a direct call without the receipt permission to be denied.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_authenticated_caller_cannot_impersonate_another_service_actor(): void
    {
        $caller = User::factory()->create();
        $impersonated = User::factory()->create();
        $impersonated->grantPermission('inventori.receipt.create');
        [$item, $location] = $this->createStockContext();
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        try {
            $this->actingAs($caller);
            app(InventoryMovementService::class)->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 2,
                'uom' => $item->uom,
                'performed_by' => $impersonated->id,
            ], $impersonated);
            $this->fail('An authenticated caller must not impersonate another mutation actor.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_inactive_application_user_cannot_authorize_a_whatsapp_style_service_call(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->grantPermission('inventori.receipt.create');
        [$item, $location] = $this->createStockContext();
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->assertReceiptDenied($user, $item, $location);
    }

    public function test_authorized_direct_receipt_requires_active_gate_and_commits_movement_and_balance(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        [$item, $location] = $this->createStockContext();
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $movement = app(InventoryMovementService::class)->receipt([
            'item_id' => $item->id,
            'lot_mode' => 'untracked',
            'location_id' => $location->id,
            'qty' => 3,
            'uom' => $item->uom,
            'performed_by' => $user->id,
        ], $user);

        $this->assertSame('RECEIPT', $movement->movement_type);
        $this->assertSame($user->id, $movement->performed_by);
        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 3,
        ]);
    }

    public function test_authorized_transfer_uses_its_action_permission_and_applies_both_locations(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.transfer.create');
        [$item, $source] = $this->createStockContext();
        $destination = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $source->id,
            'on_hand_qty' => 10,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $movement = app(InventoryMovementService::class)->transfer([
            'item_id' => $item->id,
            'lot_id' => null,
            'lot_mode' => 'untracked',
            'from_location_id' => $source->id,
            'to_location_id' => $destination->id,
            'qty' => 4,
            'uom' => $item->uom,
            'expected_on_hand_qty' => 10,
            'expected_available_qty' => 10,
            'performed_by' => $user->id,
        ], $user);

        $this->assertSame('TRANSFER', $movement->movement_type);
        $this->assertEquals(6, (float) InventoryBalance::query()->where('location_id', $source->id)->value('on_hand_qty'));
        $this->assertEquals(4, (float) InventoryBalance::query()->where('location_id', $destination->id)->value('on_hand_qty'));
    }

    public function test_transfer_uses_available_stock_and_rejects_a_stale_source_snapshot(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.transfer.create');
        [$item, $source] = $this->createStockContext();
        $destination = InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $source->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 3,
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $destination->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 1,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $service = app(InventoryMovementService::class);

        foreach ([
            ['qty' => 3, 'expected_on_hand_qty' => 5, 'expected_available_qty' => 2, 'message' => 'Tersedia: 2.000'],
            ['qty' => 1, 'expected_on_hand_qty' => 4, 'expected_available_qty' => 2, 'message' => 'berubah sejak dimuat'],
            ['qty' => 1, 'expected_on_hand_qty' => 5, 'expected_available_qty' => 2, 'lot_mode' => '', 'message' => 'Item ini untracked'],
        ] as $attempt) {
            try {
                $service->transfer([
                    'item_id' => $item->id,
                    'lot_mode' => $attempt['lot_mode'] ?? 'untracked',
                    'from_location_id' => $source->id,
                    'to_location_id' => $destination->id,
                    'qty' => $attempt['qty'],
                    'expected_on_hand_qty' => $attempt['expected_on_hand_qty'],
                    'expected_available_qty' => $attempt['expected_available_qty'],
                ], $user);
                $this->fail('Transfer must reject insufficient availability and stale source context.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($attempt['message'], $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(5.0, (float) InventoryBalance::query()->where('location_id', $source->id)->value('on_hand_qty'));
        $this->assertSame(2.0, (float) InventoryBalance::query()->where('location_id', $destination->id)->value('on_hand_qty'));
    }

    public function test_tracked_transfer_moves_one_explicit_eligible_lot_and_preserves_reservations(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.transfer.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $source = InventoryLocation::factory()->create(['is_restricted' => false]);
        $destination = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'ACTIVE']);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $source->id,
            'on_hand_qty' => 7,
            'reserved_qty' => 2,
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $destination->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 1,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $lot->update(['status' => 'QUARANTINE']);
        try {
            app(InventoryMovementService::class)->transfer([
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'from_location_id' => $source->id,
                'to_location_id' => $destination->id,
                'qty' => 1,
            ], $user);
            $this->fail('A quarantined lot must not be transferred.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('tidak dapat ditransfer', $exception->getMessage());
        }
        $this->assertDatabaseCount('inventory_movements', 0);

        $lot->update(['status' => 'ACTIVE']);

        $movement = app(InventoryMovementService::class)->transfer([
            'item_id' => $item->id,
            'lot_mode' => 'existing',
            'lot_id' => $lot->id,
            'from_location_id' => $source->id,
            'to_location_id' => $destination->id,
            'qty' => 4,
            'expected_on_hand_qty' => 7,
            'expected_available_qty' => 5,
        ], $user);

        $this->assertSame('TRANSFER', $movement->movement_type);
        $this->assertSame($lot->id, $movement->lot_id);
        $this->assertSame(10.0, (float) InventoryBalance::query()->sum('on_hand_qty'));
        $this->assertSame(3.0, (float) InventoryBalance::query()->where('location_id', $source->id)->value('on_hand_qty'));
        $this->assertSame(7.0, (float) InventoryBalance::query()->where('location_id', $destination->id)->value('on_hand_qty'));
        $this->assertSame(2.0, (float) InventoryBalance::query()->where('location_id', $source->id)->value('reserved_qty'));
        $this->assertSame(1.0, (float) InventoryBalance::query()->where('location_id', $destination->id)->value('reserved_qty'));
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_transfer_rolls_back_ledger_and_source_when_destination_projection_fails(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.transfer.create');
        [$item, $source] = $this->createStockContext();
        $destination = InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $source->id,
            'on_hand_qty' => 8,
            'reserved_qty' => 2,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $eventKey = 'eloquent.updating: '.InventoryBalance::class;
        $dispatcher = InventoryBalance::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (InventoryBalance $balance) use ($destination): void {
            if ((int) $balance->location_id === $destination->id) {
                throw new \RuntimeException('destination projection failed');
            }
        });

        try {
            app(InventoryMovementService::class)->transfer([
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'from_location_id' => $source->id,
                'to_location_id' => $destination->id,
                'qty' => 4,
            ], $user);
            $this->fail('A destination projection failure must roll back the complete transfer.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('destination projection failed', $exception->getMessage());
        } finally {
            $dispatcher->forget($eventKey);
        }

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseMissing('inventory_balances', ['item_id' => $item->id, 'location_id' => $destination->id]);
        $this->assertSame(8.0, (float) InventoryBalance::query()->where('location_id', $source->id)->value('on_hand_qty'));
    }

    public function test_issue_cannot_consume_reserved_quantity_even_when_on_hand_is_sufficient(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.issue.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'ACTIVE']);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 3,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryMovementService::class)->issue([
                'item_id' => $item->id,
                'lot_id' => $lot->id,
                'lot_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 3,
                'performed_by' => $user->id,
            ], $user);
            $this->fail('Issue quantity must be compared with available, not on-hand stock.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Tersedia: 2.000', $exception->getMessage());
            $this->assertSame(5.0, (float) InventoryBalance::query()->value('on_hand_qty'));
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_issue_requires_an_explicit_eligible_lot_and_confirms_the_committed_tuple(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.issue.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'ISSUE-CONFIRM-LOT', 'status' => 'ACTIVE']);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 1,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.issue'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'qty' => 2,
                'reference_type' => 'MANUAL',
            ])
            ->assertRedirect(route('inventory.transaction.issue'))
            ->assertSessionHas('issue_confirmation', fn (array $confirmation): bool => $confirmation['item_name'] === $item->name
                && $confirmation['lot_mode'] === 'existing'
                && $confirmation['lot_no'] === $lot->lot_no
                && $confirmation['location_id'] === $location->id
                && (float) $confirmation['qty'] === 2.0
                && (float) $confirmation['resulting_balance'] === 3.0
                && (float) $confirmation['resulting_available'] === 2.0
                && str_contains($confirmation['stock_card_url'], 'lot_id='.$lot->id));

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ISSUE',
            'lot_mode' => 'tracked_existing',
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'qty' => 2,
        ]);
        $this->assertEquals(3, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));

        $this->from(route('inventory.transaction.issue'))
            ->post(route('inventory.transaction.issue'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'qty' => 1,
                'expected_on_hand_qty' => 5,
                'expected_available_qty' => 4,
            ])
            ->assertSessionHasErrors('error');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertEquals(3, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
    }

    public function test_issue_rejects_missing_or_ineligible_lot_and_rolls_back_movement_failure(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.issue.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'QUARANTINE']);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->from(route('inventory.transaction.issue'))
            ->post(route('inventory.transaction.issue'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('lot_mode');
        $this->assertDatabaseCount('inventory_movements', 0);

        try {
            app(InventoryMovementService::class)->issue([
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'qty' => 1,
            ], $user);
            $this->fail('A quarantined lot must not be issued.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('tidak dapat dikeluarkan', $exception->getMessage());
        }
        $this->assertDatabaseCount('inventory_movements', 0);

        $lot->update(['status' => 'ACTIVE']);
        $eventKey = 'eloquent.updating: '.InventoryBalance::class;
        $dispatcher = InventoryBalance::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (): void {
            throw new \RuntimeException('movement failed');
        });

        try {
            app(InventoryMovementService::class)->issue([
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'qty' => 1,
            ], $user);
            $this->fail('A failed movement insert must roll back the balance projection.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('movement failed', $exception->getMessage());
        } finally {
            $dispatcher->forget($eventKey);
        }

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertEquals(5, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
    }

    public function test_untracked_issue_requires_explicit_mode_and_uses_the_null_lot_tuple(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.issue.create');
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 1,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->from(route('inventory.transaction.issue'))
            ->post(route('inventory.transaction.issue'), [
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('lot_mode');
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->post(route('inventory.transaction.issue'), [
            'item_id' => $item->id,
            'lot_mode' => 'untracked',
            'location_id' => $location->id,
            'qty' => 1.5,
            'expected_on_hand_qty' => 4,
            'expected_available_qty' => 3,
        ])
            ->assertRedirect(route('inventory.transaction.issue'))
            ->assertSessionHas('issue_confirmation', fn (array $confirmation): bool => $confirmation['lot_mode'] === 'untracked'
                && $confirmation['lot_no'] === null
                && (float) $confirmation['resulting_balance'] === 2.5
                && (float) $confirmation['resulting_available'] === 1.5);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ISSUE',
            'lot_mode' => 'untracked',
            'item_id' => $item->id,
            'lot_id' => null,
            'qty' => 1.5,
        ]);
    }

    public function test_transfer_rejects_same_or_missing_location_before_writing(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.transfer.create');
        [$item, $location] = $this->createStockContext();
        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $service = app(InventoryMovementService::class);

        foreach ([$location->id, 99999999] as $destinationId) {
            try {
                $service->transfer([
                    'item_id' => $item->id,
                    'lot_id' => null,
                    'lot_mode' => 'untracked',
                    'from_location_id' => $location->id,
                    'to_location_id' => $destinationId,
                    'qty' => 1,
                    'uom' => $item->uom,
                    'performed_by' => $user->id,
                ], $user);
                $this->fail('Transfer must reject identical or missing locations.');
            } catch (\RuntimeException) {
                $this->assertDatabaseCount('inventory_movements', 0);
            }
        }

        $this->assertSame(5.0, (float) InventoryBalance::query()->where('location_id', $location->id)->value('on_hand_qty'));
    }

    public function test_authorized_material_disposal_uses_its_action_permission(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.material-disposal.create');
        [$item, $location] = $this->createStockContext();
        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 8,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $movement = app(InventoryMovementService::class)->dispose([
            'item_id' => $item->id,
            'lot_id' => null,
            'lot_mode' => 'untracked',
            'location_id' => $location->id,
            'qty' => 2,
            'uom' => $item->uom,
            'reason_code' => 'EXPIRED',
            'review_confirmed' => true,
            'performed_by' => $user->id,
        ], $user);

        $this->assertSame('DISPOSE', $movement->movement_type);
        $this->assertEquals(6, (float) InventoryBalance::query()->where('location_id', $location->id)->value('on_hand_qty'));
    }

    public function test_disposal_requires_explicit_review_reason_and_confirms_the_committed_material(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.material-disposal.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'status' => 'QUARANTINE',
            'expiry_date' => now()->subDay()->toDateString(),
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 1,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->from(route('inventory.transaction.dispose'))
            ->post(route('inventory.transaction.dispose'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_id' => $location->id,
                'qty' => 5,
                'expected_on_hand_qty' => 5,
                'expected_available_qty' => 4,
                'reason_code' => 'EXPIRED',
                'review_confirmed' => '1',
            ])
            ->assertSessionHasErrors('error');

        $this->post(route('inventory.transaction.dispose'), [
            'item_id' => $item->id,
            'lot_mode' => 'existing',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'qty' => 4,
            'expected_on_hand_qty' => 5,
            'expected_available_qty' => 4,
            'reason_code' => 'NOT-A-REASON',
            'review_confirmed' => '1',
        ])
            ->assertSessionHasErrors('reason_code');

        $this->post(route('inventory.transaction.dispose'), [
            'item_id' => $item->id,
            'lot_mode' => 'existing',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'qty' => 4,
            'expected_on_hand_qty' => 5,
            'expected_available_qty' => 4,
            'reason_code' => 'EXPIRED',
        ])->assertSessionHasErrors('review_confirmed');
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->post(route('inventory.transaction.dispose'), [
            'item_id' => $item->id,
            'lot_mode' => 'existing',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'qty' => 4,
            'expected_on_hand_qty' => 5,
            'expected_available_qty' => 4,
            'reason_code' => 'EXPIRED',
            'review_confirmed' => '1',
        ])
            ->assertRedirect(route('inventory.transaction.dispose'))
            ->assertSessionHas('disposal_confirmation', fn (array $confirmation): bool => $confirmation['item_name'] === $item->name
                && $confirmation['lot_mode'] === 'existing'
                && $confirmation['lot_no'] === $lot->lot_no
                && $confirmation['reason_label'] === 'Kadaluarsa'
                && $confirmation['location_id'] === $location->id
                && (float) $confirmation['resulting_balance'] === 1.0
                && (float) $confirmation['resulting_available'] === 0.0
                && $confirmation['can_view_stock_card']);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'DISPOSE',
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'from_location_id' => $location->id,
            'reason_code' => 'EXPIRED',
            'performed_by' => $user->id,
            'qty' => 4,
        ]);

        $this->post(route('inventory.transaction.dispose'), [
            'item_id' => $item->id,
            'lot_mode' => 'existing',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'qty' => 1,
            'expected_on_hand_qty' => 5,
            'expected_available_qty' => 4,
            'reason_code' => 'EXPIRED',
            'review_confirmed' => '1',
        ])->assertSessionHasErrors('error');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(1.0, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
    }

    public function test_disposing_final_lot_rolls_back_movement_balance_and_lot_status_on_failure(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.material-disposal.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'EXPIRED']);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $eventKey = 'eloquent.updating: '.InventoryLot::class;
        $dispatcher = InventoryLot::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (): void {
            throw new \RuntimeException('lot status update failed');
        });

        try {
            app(InventoryMovementService::class)->dispose([
                'item_id' => $item->id,
                'lot_id' => $lot->id,
                'lot_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 3,
                'reason_code' => 'EXPIRED',
                'review_confirmed' => true,
            ], $user);
            $this->fail('A failed final-lot status update must roll back disposal effects.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('lot status update failed', $exception->getMessage());
        } finally {
            $dispatcher->forget($eventKey);
        }

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(3.0, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
        $this->assertSame('EXPIRED', $lot->fresh()->status);

        app(InventoryMovementService::class)->dispose([
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'lot_mode' => 'existing',
            'location_id' => $location->id,
            'qty' => 3,
            'reason_code' => 'EXPIRED',
            'review_confirmed' => true,
        ], $user);

        $this->assertSame('DISPOSED', $lot->fresh()->status);
        $this->assertSame(0.0, (float) InventoryBalance::query()->where('item_id', $item->id)->value('on_hand_qty'));
        $this->assertDatabaseCount('inventory_movements', 1);

        try {
            app(InventoryMovementService::class)->dispose([
                'item_id' => $item->id,
                'lot_id' => $lot->id,
                'lot_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
                'reason_code' => 'EXPIRED',
                'review_confirmed' => true,
            ], $user);
            $this->fail('A lot already marked disposed must not be disposed a second time.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('telah didisposal', $exception->getMessage());
        }
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_issue_transfer_and_disposal_reject_a_lot_owned_by_another_item(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.issue.create');
        $user->grantPermission('inventori.transfer.create');
        $user->grantPermission('inventori.material-disposal.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $destination = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 0,
            'reserved_qty' => 0,
        ]);
        $otherItem = InventoryItem::factory()->create();
        $otherLot = InventoryLot::factory()->create([
            'item_id' => $otherItem->id,
            'status' => 'ACTIVE',
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $service = app(InventoryMovementService::class);

        $operations = [
            fn () => $service->issue([
                'item_id' => $item->id,
                'lot_id' => $otherLot->id,
                'lot_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
                'uom' => $item->uom,
                'performed_by' => $user->id,
            ], $user),
            fn () => $service->transfer([
                'item_id' => $item->id,
                'lot_id' => $otherLot->id,
                'lot_mode' => 'existing',
                'from_location_id' => $location->id,
                'to_location_id' => $destination->id,
                'qty' => 1,
                'uom' => $item->uom,
                'performed_by' => $user->id,
            ], $user),
            fn () => $service->dispose([
                'item_id' => $item->id,
                'lot_id' => $otherLot->id,
                'lot_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
                'uom' => $item->uom,
                'performed_by' => $user->id,
                'reason_code' => 'OTHER',
                'review_confirmed' => true,
            ], $user),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('A transaction must reject a lot owned by another item.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Lot yang dipilih bukan milik item ini.', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(1, InventoryBalance::query()->count());
    }

    public function test_legacy_immediate_stocktake_adjustment_remains_disabled_even_when_gate_is_active(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.stocktake.submit');
        [$item, $location] = $this->createStockContext();
        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryMovementService::class)->processStocktake([[
                'item_id' => $item->id,
                'lot_id' => null,
                'location_id' => $location->id,
                'counted_qty' => 3,
            ]], $user->id, $user);
            $this->fail('The legacy immediate stocktake path must not apply adjustments.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        $this->assertEquals(1, (float) InventoryBalance::query()->where('location_id', $location->id)->value('on_hand_qty'));
    }

    public function test_legacy_stocktake_route_does_not_post_unreviewed_adjustments(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.stocktake.submit');
        [$item, $location] = $this->createStockContext();
        InventoryBalance::create([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.stocktake'), [
                'rows' => [[
                    'item_id' => $item->id,
                    'lot_id' => null,
                    'location_id' => $location->id,
                    'counted_qty' => 3,
                ]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertEquals(1, (float) InventoryBalance::query()->where('location_id', $location->id)->value('on_hand_qty'));
    }

    public function test_receipt_cannot_create_a_location_outside_master_locations(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_mode' => 'new',
                'new_location_name' => 'Unauthorized Receipt Location',
                'new_location_type' => 'storage',
                'qty' => 1,
            ])
            ->assertSessionHasErrors('location_mode');

        $this->assertSame(
            'Tambahkan dan beri akses lokasi terlebih dahulu melalui Master Lokasi.',
            session('errors')->first('location_mode'),
        );

        $this->assertDatabaseMissing('inventory_locations', ['name' => 'Unauthorized Receipt Location']);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_new_lot_receipt_requires_master_create_permission_before_creating_the_lot(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'new',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'new_lot_no' => 'LOT-NO-CREATE-PERMISSION',
                'qty' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('inventory_lots', ['lot_no' => 'LOT-NO-CREATE-PERMISSION']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_disabled_gate_rejects_new_lot_receipt_without_orphan_lot(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'new',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'new_lot_no' => 'LOT-GATE-DISABLED',
                'qty' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('inventory_lots', ['lot_no' => 'LOT-GATE-DISABLED']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_new_lot_receipt_rolls_back_the_lot_when_movement_creation_fails(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $eventKey = 'eloquent.creating: '.InventoryMovement::class;
        $dispatcher = InventoryMovement::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (): void {
            throw new \RuntimeException('movement failed');
        });

        try {
            $this->actingAs($user)
                ->post(route('inventory.transaction.receipt'), [
                    'item_id' => $item->id,
                    'lot_mode' => 'new',
                    'new_lot_no' => 'LOT-ROLLBACK',
                    'location_mode' => 'existing',
                    'location_id' => $location->id,
                    'qty' => 1,
                ])
                ->assertSessionHasErrors('error');
        } finally {
            $dispatcher->forget($eventKey);
        }

        $this->assertDatabaseMissing('inventory_lots', ['lot_no' => 'LOT-ROLLBACK']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_untracked_receipt_requires_explicit_mode_and_returns_verifiable_confirmation(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.receipt.create');
        $item = InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 2.5,
                'reference_type' => 'MANUAL',
            ])
            ->assertRedirect(route('inventory.transaction.receipt'))
            ->assertSessionHas('receipt_confirmation', fn (array $confirmation): bool => $confirmation['item_name'] === $item->name
                && $confirmation['lot_mode'] === 'untracked'
                && $confirmation['location_id'] === $location->id
                && (float) $confirmation['qty'] === 2.5
                && str_contains($confirmation['stock_card_url'], 'item_id='.$item->id)
            );

        $movement = InventoryMovement::query()->firstOrFail();
        $this->assertSame('untracked', $movement->lot_mode);
        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 2.5,
        ]);

        $trackedItem = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $this->post(route('inventory.transaction.receipt'), [
            'item_id' => $trackedItem->id,
            'location_mode' => 'existing',
            'location_id' => $location->id,
            'qty' => 1,
        ])->assertSessionHasErrors('lot_mode');
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_lot_tracked_receipt_requires_one_existing_lot_and_returns_movement_confirmation(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.view');
        $user->grantPermission('inventori.receipt.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'RECEIPT-CONFIRM-LOT', 'status' => 'ACTIVE']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1.25,
                'reference_type' => 'MANUAL',
            ])
            ->assertRedirect(route('inventory.transaction.receipt'))
            ->assertSessionHas('receipt_confirmation', fn (array $confirmation): bool => $confirmation['lot_mode'] === 'existing'
                && $confirmation['lot_no'] === $lot->lot_no
                && $confirmation['movement_id'] > 0
                && (float) $confirmation['resulting_balance'] === 1.25
                && str_contains($confirmation['stock_card_url'], 'lot_id='.$lot->id)
            );

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RECEIPT',
            'lot_mode' => 'tracked_existing',
            'item_id' => $item->id,
            'lot_id' => $lot->id,
            'qty' => 1.25,
        ]);
    }

    public function test_unapproved_or_mismatched_lot_policy_blocks_receipt_without_stock_writes(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->from(route('inventory.transaction.receipt'))
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'untracked',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);

        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'ACTIVE']);
        DB::table('inventory_lot_tracking_policies')->where('item_type', 'REAGENT')->update([
            'lot_tracking_mode' => null,
            'evidence_reference' => null,
            'decided_by_user_id' => null,
            'decided_at' => null,
        ]);
        $this->from(route('inventory.transaction.receipt'))
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('error');
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_receipt_service_fails_closed_for_unclassified_or_missing_tracked_lot(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $service = app(InventoryMovementService::class);

        try {
            $service->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 1,
                'lot_mode' => 'untracked',
            ], $user);
            $this->fail('A tracked item must reject an untracked lot mode.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        DB::table('inventory_lot_tracking_policies')->where('item_type', 'REAGENT')->update([
            'lot_tracking_mode' => null,
            'evidence_reference' => null,
            'decided_by_user_id' => null,
            'decided_at' => null,
        ]);
        try {
            $service->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 1,
                'lot_mode' => 'existing',
            ], $user);
            $this->fail('An unclassified policy must reject a direct receipt service call.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
        }
    }

    public function test_new_reagent_lot_requires_expiry_before_any_lot_or_stock_write(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'uom' => 'g']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->from(route('inventory.transaction.receipt'))
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'new',
                'new_lot_no' => 'REAGENT-LOT-EXPIRY-REQUIRED',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('new_lot_expiry');

        $this->assertDatabaseMissing('inventory_lots', ['lot_no' => 'REAGENT-LOT-EXPIRY-REQUIRED']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);

        $this->from(route('inventory.transaction.receipt'))
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'new',
                'new_lot_no' => 'REAGENT-LOT-PAST-EXPIRY',
                'new_lot_expiry' => now()->subDay()->toDateString(),
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('new_lot_expiry');
        $this->assertDatabaseMissing('inventory_lots', ['lot_no' => 'REAGENT-LOT-PAST-EXPIRY']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_receipt_rejects_existing_lot_id_combined_with_new_lot_mode(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $existingLot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'LOT-EXISTING',
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'new',
                'lot_id' => $existingLot->id,
                'new_lot_no' => 'LOT-NEW-CONTRADICTORY',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('lot_id');

        $this->assertDatabaseMissing('inventory_lots', ['lot_no' => 'LOT-NEW-CONTRADICTORY']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_receipt_rejects_a_lot_owned_by_another_item(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $otherItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create([
            'item_id' => $otherItem->id,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('lot_id');

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_master_lot_creation_preserves_entered_value_and_rejects_trim_only_duplicate_for_same_item(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE']);
        $existingLot = InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'MASTER-LOT-1',
            'status' => 'DISPOSED',
        ]);

        $this->actingAs($user)
            ->post(route('inventory.lots.store'), [
                'item_id' => $item->id,
                'lot_no' => '  MASTER-LOT-1  ',
            ])
            ->assertSessionHasErrors('lot_no');

        $this->assertDatabaseCount('inventory_lots', 1);
        $this->assertDatabaseHas('inventory_lots', ['id' => $existingLot->id, 'lot_no' => 'MASTER-LOT-1']);

        $this->actingAs($user)
            ->post(route('inventory.lots.store'), [
                'item_id' => $item->id,
                'lot_no' => '  MASTER-LOT-2  ',
            ])
            ->assertRedirect(route('inventory.items.lots', $item));

        $this->assertDatabaseHas('inventory_lots', ['item_id' => $item->id, 'lot_no' => '  MASTER-LOT-2  ']);

        $this->actingAs($user)
            ->post(route('inventory.lots.store'), [
                'item_id' => $item->id,
                'lot_no' => '   ',
            ])
            ->assertSessionHasErrors('lot_no');

        $this->assertDatabaseCount('inventory_lots', 2);
    }

    public function test_new_lot_receipt_requires_a_non_empty_lot_number_without_writes(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->from(route('inventory.transaction.receipt'))
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'new',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertRedirect(route('inventory.transaction.receipt'))
            ->assertSessionHasErrors('new_lot_no');

        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_new_lot_receipt_rejects_canonical_duplicate_without_stock_writes_and_allows_another_item(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $user->grantPermission('inventori.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $otherItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryLot::factory()->create([
            'item_id' => $item->id,
            'lot_no' => 'RECEIPT-LOT-1',
            'status' => 'DISPOSED',
        ]);
        InventoryMutationMode::query()->findOrFail(1)->update(['mode' => InventoryMutationMode::ACTIVE]);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'new',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'new_lot_no' => ' RECEIPT-LOT-1 ',
                'qty' => 1,
            ])
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('inventory_lots', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $otherItem->id,
                'lot_mode' => 'new',
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'new_lot_no' => ' RECEIPT-LOT-1 ',
                'qty' => 1,
            ])
            ->assertRedirect(route('inventory.transaction.receipt'));

        $this->assertDatabaseHas('inventory_lots', ['item_id' => $otherItem->id, 'lot_no' => ' RECEIPT-LOT-1 ']);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('inventory_balances', 1);
    }

    public function test_postgresql_enforces_item_scoped_trim_only_lot_identity(): void
    {
        $item = InventoryItem::factory()->create();
        $otherItem = InventoryItem::factory()->create();
        InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'INDEX-LOT-1']);
        InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'index-lot-1']);
        InventoryLot::factory()->create(['item_id' => $otherItem->id, 'lot_no' => 'INDEX-LOT-1']);

        $this->expectException(QueryException::class);
        DB::table('inventory_lots')->insert([
            'item_id' => $item->id,
            'lot_no' => ' INDEX-LOT-1 ',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_empty_canonical_lot_identity(): void
    {
        $item = InventoryItem::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('inventory_lots')->insert([
            'item_id' => $item->id,
            'lot_no' => '   ',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_receipt_rejects_existing_non_active_lot_without_stock_mutation(): void
    {
        $user = User::factory()->create();
        $user->grantPermission('inventori.receipt.create');
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'uom' => 'pcs']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $lot = InventoryLot::factory()->create(['item_id' => $item->id, 'status' => 'QUARANTINE']);

        $this->actingAs($user)
            ->post(route('inventory.transaction.receipt'), [
                'item_id' => $item->id,
                'lot_mode' => 'existing',
                'lot_id' => $lot->id,
                'location_mode' => 'existing',
                'location_id' => $location->id,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('lot_id');

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_lot_identity_preflight_reports_legacy_collisions_without_modifying_records(): void
    {
        $item = InventoryItem::factory()->create();
        $firstLot = InventoryLot::factory()->create(['item_id' => $item->id, 'lot_no' => 'LEGACY-LOT-1']);
        DB::statement('DROP INDEX inventory_lots_item_canonical_no_unique');
        $secondLotId = DB::table('inventory_lots')->insertGetId([
            'item_id' => $item->id,
            'lot_no' => ' LEGACY-LOT-1 ',
            'status' => 'DISPOSED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lotCount = InventoryLot::query()->count();
        $exitCode = Artisan::call('inventory:lots:preflight-identity');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString((string) $item->id, $output);
        $this->assertStringContainsString((string) $firstLot->id, $output);
        $this->assertStringContainsString((string) $secondLotId, $output);
        $this->assertStringContainsString('LEGACY-LOT-1', $output);
        $this->assertSame($lotCount, InventoryLot::query()->count());
        $this->assertDatabaseHas('inventory_lots', ['id' => $firstLot->id, 'lot_no' => 'LEGACY-LOT-1']);
        $this->assertDatabaseHas('inventory_lots', ['id' => $secondLotId, 'lot_no' => ' LEGACY-LOT-1 ']);

        $migration = require database_path('migrations/2026_10_02_000001_enforce_inventory_lot_identity_per_item.php');
        try {
            $migration->up();
            $this->fail('Expected unresolved legacy lot collisions to block unique-index creation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString((string) $firstLot->id, $exception->getMessage());
            $this->assertStringContainsString((string) $secondLotId, $exception->getMessage());
        }

        $index = DB::selectOne("SELECT to_regclass('public.inventory_lots_item_canonical_no_unique') AS index_name");
        $this->assertNull($index->index_name);
    }

    public function test_lot_identity_preflight_reports_empty_canonical_values_without_modifying_records(): void
    {
        $item = InventoryItem::factory()->create();
        DB::statement('DROP INDEX inventory_lots_item_canonical_no_unique');
        DB::statement('ALTER TABLE inventory_lots DROP CONSTRAINT inventory_lots_canonical_lot_no_nonempty_check');
        $lotId = DB::table('inventory_lots')->insertGetId([
            'item_id' => $item->id,
            'lot_no' => '   ',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('inventory:lots:preflight-identity');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Empty canonical identity', $output);
        $this->assertStringContainsString((string) $lotId, $output);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lotId, 'lot_no' => '   ']);

        $migration = require database_path('migrations/2026_10_02_000001_enforce_inventory_lot_identity_per_item.php');
        try {
            $migration->up();
            $this->fail('Expected an empty canonical lot identity to block constraint deployment.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString((string) $lotId, $exception->getMessage());
        }

        $index = DB::selectOne("SELECT to_regclass('public.inventory_lots_item_canonical_no_unique') AS index_name");
        $this->assertNull($index->index_name);
    }

    /** @return array{InventoryItem, InventoryLocation} */
    private function createStockContext(): array
    {
        return [
            InventoryItem::factory()->create(['item_type' => 'OTHER', 'uom' => 'pcs']),
            InventoryLocation::factory()->create(['is_restricted' => false]),
        ];
    }

    private function assertReceiptDenied(User $user, InventoryItem $item, InventoryLocation $location): void
    {
        $locationCount = InventoryLocation::query()->count();
        $lotCount = InventoryLot::query()->count();
        $auditCount = ActivityLog::query()->count();

        try {
            app(InventoryMovementService::class)->receipt([
                'item_id' => $item->id,
                'location_id' => $location->id,
                'qty' => 1,
                'uom' => $item->uom,
                'performed_by' => $user->id,
            ], $user);
            $this->fail('Expected an invalid/missing gate state to deny the receipt.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        $this->assertDatabaseCount('inventory_locations', $locationCount);
        $this->assertDatabaseCount('inventory_lots', $lotCount);
        $this->assertDatabaseCount('activity_logs', $auditCount);
    }
}
