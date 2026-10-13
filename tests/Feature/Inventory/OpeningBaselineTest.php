<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\InventoryOpeningBaseline;
use App\Models\InventoryOpeningBaselineLine;
use App\Models\User;
use App\Services\InventoryLotTrackingPolicyService;
use App\Services\InventoryMutationModeService;
use App\Services\InventoryOpeningBaselineService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpeningBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_only_an_authorized_administrator_can_start_opening_baseline_mode(): void
    {
        $counter = $this->counter();
        $modeService = app(InventoryMutationModeService::class);

        try {
            $modeService->beginOpeningBaseline($counter);
            $this->fail('Expected missing access-management permission to deny the mode transition.');
        } catch (AuthorizationException) {
            $this->assertSame(InventoryMutationMode::DISABLED, InventoryMutationMode::query()->findOrFail(1)->mode);
            $this->assertDatabaseCount('inventory_mutation_mode_events', 0);
        }

        $counter->grantPermission('inventori.location-access.manage');
        $this->assertTrue($modeService->beginOpeningBaseline($counter, 'Persiapan hitung fisik'));
        $this->assertFalse($modeService->beginOpeningBaseline($counter, 'Pengulangan'));
        $this->assertSame(InventoryMutationMode::OPENING_BASELINE, InventoryMutationMode::query()->findOrFail(1)->mode);
        $this->assertDatabaseCount('inventory_mutation_mode_events', 1);
        $this->assertDatabaseHas('inventory_mutation_mode_events', [
            'actor_user_id' => $counter->id,
            'previous_mode' => InventoryMutationMode::DISABLED,
            'new_mode' => InventoryMutationMode::OPENING_BASELINE,
            'reason' => 'Persiapan hitung fisik',
        ]);
    }

    public function test_baseline_index_explains_disabled_mode_and_exposes_start_only_to_manager(): void
    {
        $counter = User::factory()->create(['role' => 'penyelia']);
        $counter->grantPermission('inventori.view');
        $counter->grantPermission('inventori.stocktake.submit');

        $this->actingAs($counter)
            ->get(route('inventory.opening-baseline.index'))
            ->assertOk()
            ->assertSee('Status perubahan stok: tidak aktif')
            ->assertSee('Minta administrator memulai pencatatan stok awal')
            ->assertDontSee('Mulai pencatatan stok awal');

        $counter->grantPermission('inventori.location-access.manage');
        $this->get(route('inventory.opening-baseline.index'))
            ->assertOk()
            ->assertSee('Mulai pencatatan stok awal');

        $this->post(route('inventory.opening-baseline.start'), ['reason' => 'Persiapan awal'])
            ->assertRedirect(route('inventory.opening-baseline.index'))
            ->assertSessionHas('success');
        $this->assertSame(InventoryMutationMode::OPENING_BASELINE, InventoryMutationMode::query()->findOrFail(1)->mode);
        $this->assertDatabaseHas('inventory_mutation_mode_events', [
            'actor_user_id' => $counter->id,
            'reason' => 'Persiapan awal',
        ]);
    }

    public function test_lot_policy_changes_are_evidence_backed_and_stale_the_manifest(): void
    {
        $counter = $this->counter();
        $this->startBaselineMode($counter);
        InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($counter);
        $policyPage = $this->actingAs($counter)->get(route('inventory.baseline.policies'))->assertOk();
        $policyPage->assertSee('Belum ditetapkan');

        $this->put(route('inventory.baseline.policies.update'), [
            'policies' => ['CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED],
            'evidence_reference' => 'Keputusan owner INV-2',
        ])->assertRedirect(route('inventory.baseline.policies'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_lot_tracking_policies', [
            'item_type' => 'CONSUMABLE',
            'lot_tracking_mode' => InventoryLotTrackingPolicy::UNTRACKED,
            'decided_by_user_id' => $counter->id,
            'evidence_reference' => 'Keputusan owner INV-2',
        ]);
        $this->assertDatabaseHas('inventory_lot_tracking_policy_events', [
            'item_type' => 'CONSUMABLE',
            'actor_user_id' => $counter->id,
            'previous_lot_tracking_mode' => null,
            'lot_tracking_mode' => InventoryLotTrackingPolicy::UNTRACKED,
            'evidence_reference' => 'Keputusan owner INV-2',
        ]);

        $this->get(route('inventory.opening-baseline.show', $baseline))
            ->assertOk()
            ->assertViewHas('baseline', fn (InventoryOpeningBaseline $shown): bool => $shown->status === InventoryOpeningBaseline::STATUS_STALE);
        $this->assertDatabaseCount('inventory_lot_tracking_policy_events', 1);
    }

    public function test_lot_policy_cannot_change_after_routine_mutations_are_enabled(): void
    {
        $manager = $this->counter();
        $manager->grantPermission('inventori.location-access.manage');
        InventoryMutationMode::query()->findOrFail(InventoryMutationMode::SINGLETON_ID)->update(['mode' => InventoryMutationMode::ACTIVE]);

        try {
            app(InventoryLotTrackingPolicyService::class)->updateMatrix($manager, [
                'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            ], 'Keputusan owner tidak boleh diterapkan saat aktif');
            $this->fail('Expected the active inventory mode to reject a lot-policy update.');
        } catch (AuthorizationException) {
            $this->assertNull(InventoryLotTrackingPolicy::query()->where('item_type', 'CONSUMABLE')->value('lot_tracking_mode'));
            $this->assertDatabaseCount('inventory_lot_tracking_policy_events', 0);
        }
    }

    public function test_unclassified_active_types_block_manifest_without_inventing_lot_tuples(): void
    {
        $counter = $this->counter();
        $this->startBaselineMode($counter);
        InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryItem::factory()->create(['item_type' => 'REAGENT', 'is_active' => true]);

        $baseline = app(InventoryOpeningBaselineService::class)->generate($counter);

        $this->assertSame(InventoryOpeningBaseline::STATUS_BLOCKED, $baseline->status);
        $this->assertSame('REAGENT', $baseline->blockers['unclassified_item_types'][0]['item_type']);
        $this->assertSame(0, $baseline->tuple_count);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);

        $this->actingAs($counter)
            ->get(route('inventory.opening-baseline.show', $baseline))
            ->assertOk()
            ->assertSee('Penggunaan nomor lot belum ditentukan');
    }

    public function test_baseline_routes_generate_manifest_and_capture_zero_count(): void
    {
        $counter = $this->counter();
        $this->startBaselineMode($counter);
        $this->approveCompleteLotPolicy($counter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);

        $generateResponse = $this->actingAs($counter)
            ->post(route('inventory.opening-baseline.generate'))
            ->assertSessionHas('success');
        $baseline = InventoryOpeningBaseline::query()->latest('id')->firstOrFail();
        $generateResponse->assertRedirect(route('inventory.opening-baseline.show', $baseline));
        $line = $baseline->lines()->where('item_id', $item->id)->where('location_id', $location->id)->firstOrFail();

        $this->from(route('inventory.opening-baseline.show', $baseline))
            ->put(route('inventory.opening-baseline.lines.capture', [$baseline, $line]), ['physical_qty' => 0])
            ->assertRedirect(route('inventory.opening-baseline.show', $baseline))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_opening_baseline_lines', [
            'id' => $line->id,
            'physical_qty' => 0,
            'balance_existed' => false,
            'counted_by' => $counter->id,
        ]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_manifest_is_deduplicated_union_and_zero_capture_never_mutates_stock(): void
    {
        $counter = $this->counter();
        $this->startBaselineMode($counter);
        $this->approveCompleteLotPolicy($counter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $trackedItem = InventoryItem::factory()->create(['item_type' => 'REAGENT', 'is_active' => true]);
        $untrackedItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $inactiveItem = InventoryItem::factory()->create(['item_type' => 'OTHER', 'is_active' => false]);
        $movementLot = InventoryLot::factory()->create([
            'item_id' => $trackedItem->id,
            'lot_no' => 'BASELINE-QUARANTINE-01',
            'status' => 'QUARANTINE',
        ]);
        $catalogLot = InventoryLot::factory()->create([
            'item_id' => $trackedItem->id,
            'lot_no' => 'BASELINE-ACTIVE-01',
            'status' => 'ACTIVE',
        ]);
        InventoryLot::factory()->create([
            'item_id' => $trackedItem->id,
            'lot_no' => 'BASELINE-DISPOSED-01',
            'status' => 'DISPOSED',
        ]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $trackedItem->id,
            'lot_id' => $movementLot->id,
            'to_location_id' => $location->id,
            'qty' => 1,
            'uom' => $trackedItem->uom,
            'performed_by' => $counter->id,
            'performed_at' => now()->subMinute(),
        ]);
        InventoryMovement::query()->create([
            'movement_type' => 'ISSUE',
            'reference_type' => 'MANUAL',
            'item_id' => $trackedItem->id,
            'lot_id' => $movementLot->id,
            'from_location_id' => $location->id,
            'qty' => 1,
            'uom' => $trackedItem->uom,
            'performed_by' => $counter->id,
            'performed_at' => now(),
        ]);
        InventoryBalance::query()->create([
            'item_id' => $untrackedItem->id,
            'location_id' => $location->id,
            'on_hand_qty' => 0,
            'reserved_qty' => 0,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $inactiveItem->id,
            'location_id' => $location->id,
            'on_hand_qty' => 0,
            'reserved_qty' => 0,
        ]);

        $balanceCount = InventoryBalance::query()->count();
        $movementCount = InventoryMovement::query()->count();
        $baseline = app(InventoryOpeningBaselineService::class)->generate($counter);
        $lines = $baseline->lines()->get()->keyBy(fn (InventoryOpeningBaselineLine $line): string => implode('|', [
            $line->item_id,
            $line->lot_id ?? 'null',
            $line->location_id,
        ]));

        $this->assertSame(InventoryOpeningBaseline::STATUS_DRAFT, $baseline->status);
        $this->assertCount(4, $lines);
        $this->assertArrayHasKey(implode('|', [$trackedItem->id, $movementLot->id, $location->id]), $lines->all());
        $this->assertArrayHasKey(implode('|', [$trackedItem->id, $catalogLot->id, $location->id]), $lines->all());
        $this->assertArrayHasKey(implode('|', [$untrackedItem->id, 'null', $location->id]), $lines->all());
        $this->assertArrayHasKey(implode('|', [$inactiveItem->id, 'null', $location->id]), $lines->all());
        $this->assertSame(0.0, (float) $lines->get(implode('|', [$trackedItem->id, $movementLot->id, $location->id]))->system_on_hand_qty);
        $this->assertFalse($lines->get(implode('|', [$trackedItem->id, $movementLot->id, $location->id]))->balance_existed);
        $this->assertDatabaseCount('inventory_lots', 3);

        foreach ($lines as $line) {
            $physicalQty = $line->item_id === $trackedItem->id && $line->lot_id === $catalogLot->id ? 2.5 : 0;
            app(InventoryOpeningBaselineService::class)->capture($counter, $baseline->id, $line->id, $physicalQty);
        }

        $this->assertSame(4, $baseline->fresh()->counted_count);
        $this->assertSame($balanceCount, InventoryBalance::query()->count());
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertDatabaseHas('inventory_opening_baseline_lines', [
            'baseline_id' => $baseline->id,
            'item_id' => $untrackedItem->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'physical_qty' => 0,
            'balance_existed' => true,
            'counted_by' => $counter->id,
        ]);
        $this->assertDatabaseHas('inventory_opening_baseline_lines', [
            'baseline_id' => $baseline->id,
            'item_id' => $trackedItem->id,
            'lot_id' => $catalogLot->id,
            'location_id' => $location->id,
            'physical_qty' => 2.5,
            'counted_by' => $counter->id,
        ]);
    }

    public function test_restricted_location_blocker_hides_hidden_tuple_values_from_counter(): void
    {
        $counter = $this->counter();
        $this->startBaselineMode($counter);
        $this->approveCompleteLotPolicy($counter);
        $location = InventoryLocation::factory()->create([
            'name' => 'Hidden Baseline Storage',
            'is_restricted' => true,
        ]);
        $item = InventoryItem::factory()->create([
            'name' => 'Hidden Baseline Reagent',
            'item_type' => 'CONSUMABLE',
            'is_active' => true,
        ]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 8,
            'reserved_qty' => 0,
        ]);

        $baseline = app(InventoryOpeningBaselineService::class)->generate($counter);
        $visibleData = app(InventoryOpeningBaselineService::class)->show($counter, $baseline->id);

        $this->assertSame(InventoryOpeningBaseline::STATUS_BLOCKED, $baseline->status);
        $this->assertTrue($baseline->blockers['restricted_location_view_grants_missing']);
        $this->assertSame([$location->id], $baseline->blockers['restricted_location_ids_missing_view_grant']);
        $this->assertSame(0, $visibleData['visible_line_count']);
        $this->assertDatabaseCount('inventory_opening_baseline_lines', 0);

        $this->actingAs($counter)
            ->get(route('inventory.opening-baseline.show', $baseline))
            ->assertOk()
            ->assertSee('Anda belum mendapat izin untuk melihat lokasi tertentu')
            ->assertSee('ID: '.$location->id)
            ->assertDontSee('Hidden Baseline Storage')
            ->assertDontSee('Hidden Baseline Reagent')
            ->assertDontSee('8.000');
    }

    public function test_database_null_lot_uniqueness_prevents_duplicate_balance_tuples(): void
    {
        $counter = $this->counter();
        $this->startBaselineMode($counter);
        $this->approveCompleteLotPolicy($counter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        DB::table('inventory_balances')->insert([
            'item_id' => $item->id,
            'lot_id' => null,
            'location_id' => $location->id,
            'on_hand_qty' => 1,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 1,
            'uom' => $item->uom,
            'performed_by' => $counter->id,
        ]);

        try {
            DB::transaction(fn () => DB::table('inventory_balances')->insert([
                'item_id' => $item->id,
                'lot_id' => null,
                'location_id' => $location->id,
                'on_hand_qty' => 2,
                'reserved_qty' => 0,
                'updated_at' => now(),
            ]));
            $this->fail('PostgreSQL must reject a duplicate null-lot tuple.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('inventory_balances', 1);
        }

        $baseline = app(InventoryOpeningBaselineService::class)->generate($counter);

        $this->assertSame(InventoryOpeningBaseline::STATUS_DRAFT, $baseline->status, json_encode($baseline->blockers));
        $this->assertSame(1, $baseline->tuple_count);
        $this->assertDatabaseCount('inventory_balances', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_manifest_becomes_stale_after_catalog_change_and_stale_count_is_rejected(): void
    {
        $counter = $this->counter();
        $counter->grantPermission('inventori.edit');
        $this->startBaselineMode($counter);
        $this->approveCompleteLotPolicy($counter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($counter);
        $line = $baseline->lines()->where('item_id', $item->id)->where('location_id', $location->id)->firstOrFail();

        app(\App\Services\InventoryLocationConfigurationService::class)->update($counter, $location->id, [
            'name' => 'Lokasi Setelah Manifest',
            'location_type' => $location->location_type,
        ]);

        try {
            app(InventoryOpeningBaselineService::class)->capture($counter, $baseline->id, $line->id, 0);
            $this->fail('Expected stale manifest capture to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('baseline', $exception->errors());
        }

        $this->assertSame(InventoryOpeningBaseline::STATUS_STALE, $baseline->fresh()->status);
        $this->assertNull($line->fresh()->physical_qty);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    private function counter(): User
    {
        $counter = User::factory()->create(['role' => 'penyelia']);
        $counter->grantPermission('inventori.view');
        $counter->grantPermission('inventori.stocktake.submit');

        return $counter;
    }

    private function startBaselineMode(User $counter): void
    {
        $counter->grantPermission('inventori.location-access.manage');
        app(InventoryMutationModeService::class)->beginOpeningBaseline($counter, 'Tes baseline');
    }

    private function approveCompleteLotPolicy(User $counter): void
    {
        $counter->grantPermission('inventori.location-access.manage');
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($counter, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ], 'Persetujuan pemilik inventori untuk fixture PostgreSQL');
    }
}
