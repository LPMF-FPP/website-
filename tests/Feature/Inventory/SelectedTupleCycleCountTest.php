<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryCycleCount;
use App\Models\InventoryCycleCountRevision;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\User;
use App\Services\InventoryCycleCountService;
use App\Services\InventoryLotTrackingPolicyService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SelectedTupleCycleCountTest extends TestCase
{
    use RefreshDatabase;
    use SeedsInventoryReservations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->prepareActiveInventoryMode();
    }

    public function test_manifest_contains_only_selected_tuple_and_zero_variance_completes_without_stock_mutation(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $selectedItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter, 'Verifikasi lokasi penyimpanan');
        $available = $service->availableTuples($counter);
        $selected = collect($available)->first(fn (array $tuple): bool => $tuple['item_id'] === $selectedItem->id && $tuple['location_id'] === $location->id);
        $this->assertNotNull($selected);

        $line = $service->addTuple($counter, $count->id, $selectedItem->id, null, $location->id);
        $this->assertSame(1, $count->fresh()->tuple_count);
        try {
            $service->addTuple($counter, $count->id, $selectedItem->id, null, $location->id);
            $this->fail('Duplicate item-lot-location selection must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tuple', $exception->errors());
        }

        $service->capture($counter, $count->id, $line->id, 0);
        $completed = $service->submit($counter, $count->id);
        $this->assertSame(InventoryCycleCount::STATUS_COMPLETED, $completed->status);
        $this->assertTrue($completed->is_partial);
        $this->assertSame(1, $completed->tuple_count);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseHas('inventory_cycle_count_revisions', [
            'cycle_count_id' => $count->id,
            'revision_number' => 1,
            'revision_status' => 'completed',
        ]);
        $this->assertDatabaseHas('inventory_cycle_count_decisions', [
            'decision' => 'completed',
            'actor_user_id' => $counter->id,
        ]);
    }

    public function test_cycle_count_creation_fails_closed_when_gate_is_disabled_or_submit_permission_is_missing(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::DISABLED]);

        try {
            app(InventoryCycleCountService::class)->createDraft($counter);
            $this->fail('Cycle counts must be unavailable unless routine mutations are active.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_cycle_counts', 0);
        }

        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
        $readOnly = $this->userWith('inventori.view');
        try {
            app(InventoryCycleCountService::class)->createDraft($readOnly);
            $this->fail('A read-only user must not create a cycle count.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_cycle_counts', 0);
        }
    }

    public function test_variance_remains_pending_until_independent_approval_applies_one_atomic_adjustment(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit', 'inventori.stocktake.approve');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $this->recordReceipt($counter, $item, $location, 5, 1);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $line = $service->addTuple($counter, $count->id, $item->id, null, $location->id);
        $service->capture($counter, $count->id, $line->id, 7.25);
        try {
            $service->submit($counter, $count->id);
            $this->fail('A variance must include a reason before it can be submitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cycle_count', $exception->errors());
            $this->assertDatabaseCount('inventory_cycle_count_revisions', 0);
        }
        $service->capture($counter, $count->id, $line->id, 7.25, 'Fisik lebih tinggi dari catatan saldo');
        $pending = $service->submit($counter, $count->id);

        $this->assertSame(InventoryCycleCount::STATUS_PENDING_APPROVAL, $pending->status);
        $this->assertSame('Fisik lebih tinggi dari catatan saldo', $pending->revisions()->firstOrFail()->lines_snapshot[0]['variance_reason']);
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(5.0, (float) InventoryBalance::query()->value('on_hand_qty'));
        try {
            $service->capture($counter, $count->id, $line->id, 9);
            $this->fail('Submitted cycle-count lines must become read-only.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cycle_count', $exception->errors());
            $this->assertSame(7.25, (float) $line->fresh()->physical_qty);
        }
        try {
            $service->decide($counter, $count->id, true);
            $this->fail('A submitter must not approve their own cycle-count variance.');
        } catch (AuthorizationException) {
            $this->assertSame(1, InventoryMovement::query()->count());
        }

        $approved = $service->decide($approver, $count->id, true);
        $this->assertSame(InventoryCycleCount::STATUS_APPROVED, $approved->status);
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ADJUST',
            'reference_type' => 'STOCKTAKE',
            'reference_id' => $count->id,
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 2.25,
            'performed_by' => $approver->id,
            'notes' => 'Cycle count '.$count->id.' revision 1: Fisik lebih tinggi dari catatan saldo',
        ]);
        $this->assertSame(7.25, (float) InventoryBalance::query()->value('on_hand_qty'));
        $this->assertDatabaseHas('inventory_cycle_count_decisions', [
            'decision' => 'approved',
            'actor_user_id' => $approver->id,
        ]);
    }

    public function test_negative_cycle_variance_posts_an_outbound_adjustment_above_reserved_quantity(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $this->recordReceipt($counter, $item, $location, 5, 2);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $line = $service->addTuple($counter, $count->id, $item->id, null, $location->id);
        $service->capture($counter, $count->id, $line->id, 3, 'Dua unit tidak ditemukan saat hitung');
        $service->submit($counter, $count->id);
        $service->decide($approver, $count->id, true);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ADJUST',
            'reference_id' => $count->id,
            'item_id' => $item->id,
            'from_location_id' => $location->id,
            'to_location_id' => null,
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 3,
            'reserved_qty' => 2,
        ]);
    }

    public function test_approver_without_restricted_location_view_cannot_read_or_apply_cycle_variance(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $location = InventoryLocation::factory()->create(['is_restricted' => true]);
        InventoryLocationAccess::query()->create([
            'user_id' => $counter->id,
            'location_id' => $location->id,
            'can_view' => true,
            'can_transact' => true,
        ]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $line = $service->addTuple($counter, $count->id, $item->id, null, $location->id);
        $service->capture($counter, $count->id, $line->id, 3, 'Ditemukan stok fisik pada lokasi terbatas');
        $service->submit($counter, $count->id);

        try {
            $service->decide($approver, $count->id, true);
            $this->fail('Approver without view grant must not inspect the selected hidden tuple.');
        } catch (AuthorizationException) {
            $this->assertSame(InventoryCycleCount::STATUS_PENDING_APPROVAL, $count->fresh()->status);
            $this->assertSame(0, InventoryMovement::query()->count());
        }
    }

    public function test_rejection_keeps_stock_unchanged_and_allows_a_new_corrected_revision(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $line = $service->addTuple($counter, $count->id, $item->id, null, $location->id);
        $service->capture($counter, $count->id, $line->id, 2, 'Jumlah fisik berbeda dari catatan');
        $service->submit($counter, $count->id);

        $draft = $service->decide($approver, $count->id, false, 'Lokasi tidak selesai dihitung');
        $this->assertSame(InventoryCycleCount::STATUS_DRAFT, $draft->status);
        $this->assertNull($line->fresh()->physical_qty);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertDatabaseHas('inventory_cycle_count_decisions', [
            'decision' => 'rejected',
            'reason' => 'Lokasi tidak selesai dihitung',
        ]);

        $service->capture($counter, $count->id, $line->id, 0);
        $completed = $service->submit($counter, $count->id);
        $this->assertSame(InventoryCycleCount::STATUS_COMPLETED, $completed->status);
        $this->assertSame(2, $completed->revision_number);
        $revisions = InventoryCycleCountRevision::query()->where('cycle_count_id', $count->id)->orderBy('revision_number')->get();
        $this->assertSame(2, $revisions->count());
        $this->assertSame('2.000', $revisions[0]->lines_snapshot[0]['physical_qty']);
        $this->assertSame('0.000', $revisions[1]->lines_snapshot[0]['physical_qty']);
    }

    public function test_reserved_conflict_returns_entire_pending_revision_for_recount_without_partial_movement(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $firstItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $reservedItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $this->recordReceipt($counter, $reservedItem, $location, 5, 4);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $firstLine = $service->addTuple($counter, $count->id, $firstItem->id, null, $location->id);
        $reservedLine = $service->addTuple($counter, $count->id, $reservedItem->id, null, $location->id);
        $service->capture($counter, $count->id, $firstLine->id, 8, 'Selisih pada tuple pertama');
        $service->capture($counter, $count->id, $reservedLine->id, 3, 'Fisik di bawah stok yang direservasi');
        $service->submit($counter, $count->id);
        $movementCount = InventoryMovement::query()->count();
        $balanceRows = InventoryBalance::query()->orderBy('id')->get()->toArray();

        try {
            $service->decide($approver, $count->id, true);
            $this->fail('A count below reserved quantity must be returned for recount.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cycle_count', $exception->errors());
        }

        $this->assertSame(InventoryCycleCount::STATUS_DRAFT, $count->fresh()->status);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame($balanceRows, InventoryBalance::query()->orderBy('id')->get()->toArray());
        $this->assertSame('recount', $count->revisions()->latest('revision_number')->firstOrFail()->decision->decision);
    }

    public function test_missing_transact_grant_on_later_variance_tuple_rolls_back_earlier_tuple_adjustment(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $firstLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $secondLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        foreach ([$firstLocation, $secondLocation] as $location) {
            InventoryLocationAccess::query()->create([
                'user_id' => $counter->id,
                'location_id' => $location->id,
                'can_view' => true,
                'can_transact' => true,
            ]);
            InventoryLocationAccess::query()->create([
                'user_id' => $approver->id,
                'location_id' => $location->id,
                'can_view' => true,
                'can_transact' => $location->id === $firstLocation->id,
            ]);
        }
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $firstLine = $service->addTuple($counter, $count->id, $item->id, null, $firstLocation->id);
        $secondLine = $service->addTuple($counter, $count->id, $item->id, null, $secondLocation->id);
        $service->capture($counter, $count->id, $firstLine->id, 5, 'Variance pertama');
        $service->capture($counter, $count->id, $secondLine->id, 7, 'Variance kedua');
        $service->submit($counter, $count->id);

        try {
            $service->decide($approver, $count->id, true);
            $this->fail('Each restricted variance location requires transaction authority for the approver.');
        } catch (AuthorizationException) {
            $this->assertSame(InventoryCycleCount::STATUS_PENDING_APPROVAL, $count->fresh()->status);
            $this->assertDatabaseCount('inventory_movements', 0);
            $this->assertDatabaseCount('inventory_balances', 0);
            $this->assertDatabaseCount('inventory_cycle_count_decisions', 0);
        }
    }

    public function test_stale_selected_tuple_returns_revision_for_recount_and_unselected_tuple_changes_do_not_stale_it(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $selectedItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $otherItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $line = $service->addTuple($counter, $count->id, $selectedItem->id, null, $location->id);
        $service->capture($counter, $count->id, $line->id, 0);

        InventoryBalance::query()->create([
            'item_id' => $otherItem->id,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
        ]);
        $this->assertSame(InventoryCycleCount::STATUS_COMPLETED, $service->submit($counter, $count->id)->status);

        $freshCount = $service->createDraft($counter);
        $freshLine = $service->addTuple($counter, $freshCount->id, $selectedItem->id, null, $location->id);
        $service->capture($counter, $freshCount->id, $freshLine->id, 0);
        $selectedItem->update(['name' => 'Selected tuple master changed']);
        try {
            $service->submit($counter, $freshCount->id);
            $this->fail('A selected tuple source change must prevent submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cycle_count', $exception->errors());
            $this->assertSame(InventoryCycleCount::STATUS_DRAFT, $freshCount->fresh()->status);
        }
    }

    public function test_selected_snapshot_change_while_pending_returns_immutable_revision_for_recount(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $line = $service->addTuple($counter, $count->id, $item->id, null, $location->id);
        $service->capture($counter, $count->id, $line->id, 2, 'Dua unit ditemukan secara fisik');
        $revision = $service->submit($counter, $count->id)->revisions()->firstOrFail();
        $item->update(['name' => 'Nama item berubah setelah submit']);

        try {
            $service->decide($approver, $count->id, true);
            $this->fail('A selected tuple snapshot change must return the submitted revision for recount.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cycle_count', $exception->errors());
        }

        $this->assertSame(InventoryCycleCount::STATUS_DRAFT, $count->fresh()->status);
        $this->assertSame('recount', $revision->decision()->value('decision'));
        $this->assertSame('Dua unit ditemukan secara fisik', $revision->fresh()->lines_snapshot[0]['variance_reason']);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_balance_quantity_change_after_submission_returns_revision_for_recount(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $this->recordReceipt($counter, $item, $location, 5, 0);
        $service = app(InventoryCycleCountService::class);
        $count = $service->createDraft($counter);
        $line = $service->addTuple($counter, $count->id, $item->id, null, $location->id);
        $service->capture($counter, $count->id, $line->id, 6, 'Satu unit lebih banyak dari catatan');
        $revision = $service->submit($counter, $count->id)->revisions()->firstOrFail();
        InventoryBalance::query()->where('item_id', $item->id)->where('location_id', $location->id)->update(['on_hand_qty' => 4]);

        try {
            $service->decide($approver, $count->id, true);
            $this->fail('A balance quantity change after submit must block approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cycle_count', $exception->errors());
        }

        $this->assertSame(InventoryCycleCount::STATUS_DRAFT, $count->fresh()->status);
        $this->assertSame('recount', $revision->decision()->value('decision'));
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(4.0, (float) InventoryBalance::query()->value('on_hand_qty'));
    }

    public function test_route_displays_partial_scope_and_rejects_invalid_or_unauthorized_tuple_selection(): void
    {
        $counter = $this->userWith('inventori.view', 'inventori.stocktake.submit');
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $count = app(InventoryCycleCountService::class)->createDraft($counter);
        $response = $this->actingAs($counter)->get(route('inventory.cycle-counts.show', $count));
        $response->assertOk()->assertSee('Pemeriksaan sebagian stok')->assertSee('Barang yang tidak dapat Anda lihat tidak ditampilkan');

        $this->post(route('inventory.cycle-counts.tuples.store', $count), [
            'item_id' => $item->id,
            'location_id' => $location->id,
        ])->assertSessionHas('success');

        $hiddenLocation = InventoryLocation::factory()->create(['is_restricted' => true]);
        $this->post(route('inventory.cycle-counts.tuples.store', $count), [
            'item_id' => $item->id,
            'location_id' => $hiddenLocation->id,
        ])->assertForbidden();
    }

    private function prepareActiveInventoryMode(): void
    {
        $manager = $this->userWith('inventori.view', 'inventori.stocktake.submit', 'inventori.location-access.manage');
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($manager, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ], 'Kebijakan fixture cycle count');
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);
    }

    private function recordReceipt(User $actor, InventoryItem $item, InventoryLocation $location, float $quantity, float $reserved): void
    {
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => $quantity,
            'uom' => $item->uom,
            'performed_by' => $actor->id,
            'performed_at' => now(),
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => $quantity,
            'reserved_qty' => $reserved,
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['role' => 'penyelia']);
        foreach ($permissions as $permission) {
            $user->grantPermission($permission);
        }

        return $user;
    }
}
