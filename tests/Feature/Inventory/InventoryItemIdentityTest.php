<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryItemIdentityEvent;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\InventoryItemIdentityService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InventoryItemIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_item_code_is_canonicalized_and_displayed_in_the_master_list(): void
    {
        $actor = $this->userWithPermissions(['inventori.create', 'inventori.view']);

        $this->actingAs($actor)
            ->post(route('inventory.items.store'), $this->itemPayload('  rea-004.a  '))
            ->assertRedirect(route('inventory.items.index'));

        $item = InventoryItem::query()->where('name', 'Acetone Reagent')->firstOrFail();
        $this->assertSame('REA-004.A', $item->item_code);

        $this->get(route('inventory.items.index'))
            ->assertOk()
            ->assertSee('REA-004.A')
            ->assertSee('Acetone Reagent');
    }

    public function test_normalized_duplicate_and_invalid_codes_are_rejected_without_creating_items(): void
    {
        $actor = $this->userWithPermissions(['inventori.create']);
        $this->actingAs($actor);

        $this->post(route('inventory.items.store'), $this->itemPayload('rea-005'))
            ->assertRedirect(route('inventory.items.index'));

        $this->from(route('inventory.items.create'))
            ->post(route('inventory.items.store'), $this->itemPayload(' REA-005 '))
            ->assertRedirect(route('inventory.items.create'))
            ->assertSessionHasErrors('item_code');

        $this->from(route('inventory.items.create'))
            ->post(route('inventory.items.store'), $this->itemPayload('Réa-006'))
            ->assertRedirect(route('inventory.items.create'))
            ->assertSessionHasErrors('item_code');

        $this->assertDatabaseCount('inventory_items', 1);
    }

    public function test_database_enforces_unique_codes_and_canonical_format(): void
    {
        InventoryItem::factory()->create(['item_code' => 'DB-CODE-01']);

        try {
            DB::transaction(fn () => InventoryItem::factory()->create(['item_code' => 'DB-CODE-01']));
            $this->fail('Expected the database unique constraint to reject the duplicate item code.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('inventory_items', 1);
        }

        try {
            DB::transaction(fn () => InventoryItem::factory()->create(['item_code' => 'db-code-02']));
            $this->fail('Expected the database check constraint to reject a non-canonical item code.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('inventory_items', 1);
        }
    }

    public function test_similarly_named_items_keep_separate_codes_and_stable_ids(): void
    {
        $actor = $this->userWithPermissions(['inventori.view']);
        $first = InventoryItem::factory()->create(['name' => 'Same Reagent', 'item_code' => 'SAME-REAGENT-01']);
        $second = InventoryItem::factory()->create(['name' => 'Same Reagent', 'item_code' => 'SAME-REAGENT-02']);

        $this->actingAs($actor)
            ->get(route('inventory.items.index', ['search' => 'Same Reagent']))
            ->assertOk()
            ->assertSee('SAME-REAGENT-01')
            ->assertSee('SAME-REAGENT-02');

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('inventory_items', 2);
    }

    public function test_historical_item_code_change_requires_reason_and_audits_without_changing_stable_ids(): void
    {
        $actor = $this->userWithPermissions(['inventori.view', 'inventori.edit']);
        $item = InventoryItem::factory()->create(['item_code' => 'HIST-OLD-01']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $balance = InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 0,
        ]);
        $movement = InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 4,
            'uom' => $item->uom,
            'performed_by' => $actor->id,
            'performed_at' => now(),
        ]);

        $this->actingAs($actor)
            ->put(route('inventory.items.update', $item), $this->itemPayload('HIST-NEW-01'))
            ->assertSessionHasErrors('identity_change_reason');

        $this->assertSame('HIST-OLD-01', $item->fresh()->item_code);
        $this->assertDatabaseCount('inventory_item_identity_events', 0);

        $this->put(route('inventory.items.update', $item), [
            ...$this->itemPayload(' hist-new-01 '),
            'identity_change_reason' => 'Koreksi sesuai katalog pemilik.',
        ])->assertRedirect(route('inventory.items.index'));

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'item_code' => 'HIST-NEW-01',
        ]);
        $this->assertDatabaseHas('inventory_item_identity_events', [
            'inventory_item_id' => $item->id,
            'actor_user_id' => $actor->id,
            'previous_item_code' => 'HIST-OLD-01',
            'new_item_code' => 'HIST-NEW-01',
            'event_type' => 'corrected',
            'reason' => 'Koreksi sesuai katalog pemilik.',
        ]);
        $this->assertSame($item->id, $movement->fresh()->item_id);
        $this->assertSame($item->id, $balance->fresh()->item_id);
    }

    public function test_failed_identity_event_insert_rolls_back_the_code_change(): void
    {
        $actor = $this->userWithPermissions(['inventori.edit']);
        $item = InventoryItem::factory()->create(['item_code' => 'ROLLBACK-OLD']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
        ]);

        $eventKey = 'eloquent.creating: '.InventoryItemIdentityEvent::class;
        $dispatcher = InventoryItemIdentityEvent::getEventDispatcher();
        $dispatcher->listen($eventKey, static function (): void {
            throw new RuntimeException('Simulated identity event failure.');
        });

        try {
            app(InventoryItemIdentityService::class)->update(
                $item->id,
                ['item_code' => 'ROLLBACK-NEW'],
                $actor,
                'Koreksi yang gagal.',
            );
            $this->fail('Expected an audit failure to roll back the item-code update.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated identity event failure.', $exception->getMessage());
            $this->assertSame('ROLLBACK-OLD', $item->fresh()->item_code);
            $this->assertDatabaseCount('inventory_item_identity_events', 0);
        } finally {
            $dispatcher->forget($eventKey);
        }
    }

    public function test_identity_audit_events_reject_raw_update_and_truncate(): void
    {
        $actor = $this->userWithPermissions(['inventori.edit']);
        $item = InventoryItem::factory()->create(['item_code' => 'IMMUTABLE-OLD']);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
        ]);
        app(InventoryItemIdentityService::class)->update(
            $item->id,
            ['item_code' => 'IMMUTABLE-NEW'],
            $actor,
            'Perubahan terverifikasi.',
        );
        $event = InventoryItemIdentityEvent::query()->firstOrFail();

        try {
            DB::transaction(fn () => DB::table('inventory_item_identity_events')->where('id', $event->id)->update(['reason' => 'Palsu']));
            $this->fail('Expected the append-only trigger to reject event mutation.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame('Perubahan terverifikasi.', $event->fresh()->reason);
        }

        try {
            DB::transaction(fn () => DB::statement('TRUNCATE TABLE inventory_item_identity_events'));
            $this->fail('Expected the append-only trigger to reject event truncation.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('inventory_item_identity_events', 1);
        }
    }

    public function test_code_mapping_preflight_reports_normalized_collisions_without_writing_codes(): void
    {
        $first = InventoryItem::factory()->create(['item_code' => null, 'name' => 'Legacy Item One']);
        $second = InventoryItem::factory()->create(['item_code' => null, 'name' => 'Legacy Item Two']);
        $mappingPath = tempnam(sys_get_temp_dir(), 'item-code-map-');
        $malformedMappingPath = tempnam(sys_get_temp_dir(), 'item-code-map-malformed-');
        $this->assertNotFalse($mappingPath);
        $this->assertNotFalse($malformedMappingPath);
        file_put_contents($mappingPath, "item_id,item_code\n{$first->id},legacy-dup-01\n{$second->id}, LEGACY-DUP-01 \n");
        file_put_contents($malformedMappingPath, "item_id,item_code\n{$first->id},VALID-BUT-REJECTED\n\n{$second->id}\n");

        try {
            $exitCode = Artisan::call('inventory:items:preflight-code-mapping', ['mapping' => $mappingPath]);
            $output = Artisan::output();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Collision kode canonical', $output);
            $this->assertStringContainsString((string) $first->id, $output);
            $this->assertStringContainsString((string) $second->id, $output);
            $this->assertNull($first->fresh()->item_code);
            $this->assertNull($second->fresh()->item_code);

            $malformedExitCode = Artisan::call('inventory:items:preflight-code-mapping', ['mapping' => $malformedMappingPath]);
            $this->assertSame(1, $malformedExitCode);
            $this->assertStringContainsString('Baris kosong atau jumlah kolom tidak sesuai', Artisan::output());
            $this->assertNull($first->fresh()->item_code);
            $this->assertNull($second->fresh()->item_code);
        } finally {
            unlink($mappingPath);
            unlink($malformedMappingPath);
        }
    }

    public function test_item_code_is_searchable_and_shown_in_transaction_selectors(): void
    {
        $actor = $this->userWithPermissions([
            'inventori.view',
            'inventori.receipt.create',
            'inventori.issue.create',
            'inventori.transfer.create',
            'inventori.material-disposal.create',
        ]);
        $item = InventoryItem::factory()->create([
            'item_code' => 'CODE-SEARCH-22',
            'name' => 'Distinct Reagent',
            'uom' => 'bottle',
        ]);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($actor)
            ->getJson(route('inventory.ajax.search', ['q' => 'code-search-22']))
            ->assertOk()
            ->assertJsonPath('exact_match.item_id', $item->id)
            ->assertJsonPath('exact_match.label', 'CODE-SEARCH-22 · Distinct Reagent')
            ->assertJsonPath('exact_match.meta', 'bottle');

        $this->get(route('inventory.items.index', ['search' => 'code-search-22']))
            ->assertOk()
            ->assertSee('CODE-SEARCH-22');
        $this->get(route('inventory.transaction.issue'))
            ->assertOk()
            ->assertSee('CODE-SEARCH-22 · Distinct Reagent (bottle)');
        $this->get(route('inventory.transaction.receipt'))->assertOk()->assertSee('CODE-SEARCH-22 · Distinct Reagent (bottle)');
        $this->get(route('inventory.transaction.transfer'))->assertOk()->assertSee('CODE-SEARCH-22 · Distinct Reagent (bottle)');
        $this->get(route('inventory.transaction.dispose'))->assertOk()->assertSee('CODE-SEARCH-22 · Distinct Reagent (bottle)');
        $this->get(route('inventory.stock-card'))->assertOk()->assertSee('CODE-SEARCH-22 · Distinct Reagent (bottle)');
        $this->getJson(route('inventory.ajax.overview'))
            ->assertOk()
            ->assertJsonPath('data.0.item_code', 'CODE-SEARCH-22');
        $this->get(route('inventory.dashboard'))->assertOk()->assertSee('CODE-SEARCH-22');
    }

    public function test_transaction_selectors_hide_item_codes_from_ungranted_restricted_locations(): void
    {
        $actor = $this->userWithPermissions([
            'inventori.view',
            'inventori.receipt.create',
            'inventori.issue.create',
            'inventori.transfer.create',
            'inventori.stocktake.submit',
            'inventori.material-disposal.create',
        ]);
        $authorizedLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $unauthorizedLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryLocationAccess::query()->create([
            'user_id' => $actor->id,
            'location_id' => $authorizedLocation->id,
            'can_view' => false,
            'can_transact' => true,
        ]);
        $authorizedItem = InventoryItem::factory()->create([
            'item_code' => 'AUTHORIZED-ITEM-01',
            'name' => 'Authorized Reagent',
        ]);
        $hiddenItem = InventoryItem::factory()->create([
            'item_code' => 'HIDDEN-ITEM-02',
            'name' => 'Restricted Reagent',
        ]);
        InventoryBalance::query()->create([
            'item_id' => $authorizedItem->id,
            'location_id' => $authorizedLocation->id,
            'on_hand_qty' => 2,
            'reserved_qty' => 0,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $hiddenItem->id,
            'location_id' => $unauthorizedLocation->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 0,
        ]);

        $this->actingAs($actor);
        foreach ([
            'inventory.transaction.receipt',
            'inventory.transaction.issue',
            'inventory.transaction.transfer',
            'inventory.transaction.dispose',
        ] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('AUTHORIZED-ITEM-01 · Authorized Reagent (')
                ->assertDontSee('HIDDEN-ITEM-02')
                ->assertDontSee('Restricted Reagent');
        }

        $this->get(route('inventory.dashboard'))
            ->assertOk()
            ->assertDontSee('HIDDEN-ITEM-02');
    }

    /** @return array<string, string> */
    private function itemPayload(string $itemCode): array
    {
        return [
            'item_code' => $itemCode,
            'item_type' => 'REAGENT',
            'name' => 'Acetone Reagent',
            'uom' => 'bottle',
            'is_hazardous' => '0',
            'is_active' => '1',
        ];
    }

    /** @param list<string> $permissions */
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
}
