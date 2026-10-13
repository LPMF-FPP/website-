<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryMovement;
use App\Models\InventoryMutationMode;
use App\Models\InventoryOpeningBaseline;
use App\Models\InventoryOpeningBaselineApproval;
use App\Models\InventoryReadinessEvidence;
use App\Models\User;
use App\Services\InventoryLotTrackingPolicyService;
use App\Services\InventoryMutationModeService;
use App\Services\InventoryOpeningBaselineApprovalService;
use App\Services\InventoryOpeningBaselineService;
use App\Services\InventoryReadinessService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpeningBaselineApprovalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsInventoryReservations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_independent_approval_applies_variance_once_and_zero_variance_does_not_create_a_movement(): void
    {
        [$submitter, $location, $item, $baseline, $line] = $this->draftBaseline(2.5);
        $approvalService = app(InventoryOpeningBaselineApprovalService::class);
        $approvalService->submit($submitter, $baseline->id);

        $this->assertSame(InventoryOpeningBaseline::STATUS_SUBMITTED, $baseline->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);

        try {
            $approvalService->decide($submitter, $baseline->id, true);
            $this->fail('The submitter must not approve their own baseline.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('inventory_opening_baseline_approvals', 0);
            $this->assertSame(InventoryOpeningBaseline::STATUS_SUBMITTED, $baseline->fresh()->status);
        }

        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $approvalService->decide($approver, $baseline->id, true);

        $this->assertSame(InventoryOpeningBaseline::STATUS_APPROVED, $baseline->fresh()->status);
        $this->assertDatabaseHas('inventory_opening_baseline_approvals', [
            'baseline_id' => $baseline->id,
            'submission_revision' => 1,
            'submitted_by' => $submitter->id,
            'approved_by' => $approver->id,
            'decision' => 'approved',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ADJUST',
            'reference_type' => 'STOCKTAKE',
            'reference_id' => $baseline->id,
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 2.5,
            'performed_by' => $approver->id,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 2.5,
        ]);

    }

    public function test_zero_variance_approval_records_immutable_acceptance_without_adjustment(): void
    {
        [$submitter, , , $baseline] = $this->draftBaseline(0);
        $approvalService = app(InventoryOpeningBaselineApprovalService::class);
        $approvalService->submit($submitter, $baseline->id);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $approvalService->decide($approver, $baseline->id, true);

        $this->assertSame(InventoryOpeningBaseline::STATUS_APPROVED, $baseline->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->where('reference_id', $baseline->id)->count());
        $approval = InventoryOpeningBaselineApproval::query()->where('baseline_id', $baseline->id)->firstOrFail();
        $this->assertSame('approved', $approval->decision);
        $this->assertNotEmpty($approval->approved_lines_snapshot);
        $this->assertSame(['0.000'], array_values(array_unique(array_column($approval->approved_lines_snapshot, 'variance_qty'))));
    }

    public function test_negative_variance_creates_an_outbound_adjustment_and_preserves_reservations(): void
    {
        $submitter = $this->baselineManager();
        $this->classifyPolicies($submitter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 5,
            'uom' => $item->uom,
            'performed_by' => $submitter->id,
            'performed_at' => now(),
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 2,
        ]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($submitter);
        foreach ($baseline->lines as $line) {
            app(InventoryOpeningBaselineService::class)->capture($submitter, $baseline->id, $line->id, 3);
        }
        $approval = app(InventoryOpeningBaselineApprovalService::class);
        $approval->submit($submitter, $baseline->id);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $approval->decide($approver, $baseline->id, true);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'ADJUST',
            'reference_type' => 'STOCKTAKE',
            'reference_id' => $baseline->id,
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

    public function test_approver_without_restricted_location_view_cannot_approve_or_learn_hidden_stock(): void
    {
        $submitter = $this->baselineManager();
        $this->classifyPolicies($submitter);
        $location = InventoryLocation::factory()->create(['name' => 'Protected Baseline Vault', 'is_restricted' => true]);
        InventoryLocationAccess::query()->create([
            'user_id' => $submitter->id,
            'location_id' => $location->id,
            'can_view' => true,
            'can_transact' => false,
        ]);
        $item = InventoryItem::factory()->create(['name' => 'Protected Count Item', 'item_type' => 'CONSUMABLE', 'is_active' => true]);
        InventoryBalance::query()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 7,
            'reserved_qty' => 0,
        ]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 7,
            'uom' => $item->uom,
            'performed_by' => $submitter->id,
            'performed_at' => now(),
        ]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($submitter);
        foreach ($baseline->lines as $line) {
            app(InventoryOpeningBaselineService::class)->capture($submitter, $baseline->id, $line->id, 7);
        }
        $approval = app(InventoryOpeningBaselineApprovalService::class);
        $approval->submit($submitter, $baseline->id);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');

        try {
            $approval->decide($approver, $baseline->id, true);
            $this->fail('An approver without view grant must not approve restricted tuple stock.');
        } catch (AuthorizationException) {
            $this->assertSame(InventoryOpeningBaseline::STATUS_SUBMITTED, $baseline->fresh()->status);
            $this->assertDatabaseCount('inventory_opening_baseline_approvals', 0);
            $this->assertSame(1, InventoryMovement::query()->count());
            $this->assertSame(7.0, (float) InventoryBalance::query()->value('on_hand_qty'));
        }
    }

    public function test_reserved_stock_conflict_returns_baseline_for_recount_without_partial_mutation(): void
    {
        $submitter = $this->baselineManager();
        $this->classifyPolicies($submitter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $item->id,
            'to_location_id' => $location->id,
            'qty' => 5,
            'uom' => $item->uom,
            'performed_by' => $submitter->id,
            'performed_at' => now(),
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'on_hand_qty' => 5,
            'reserved_qty' => 4,
        ]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($submitter);
        $line = $baseline->lines()->firstOrFail();
        app(InventoryOpeningBaselineService::class)->capture($submitter, $baseline->id, $line->id, 3);
        $approvalService = app(InventoryOpeningBaselineApprovalService::class);
        $approvalService->submit($submitter, $baseline->id);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');

        try {
            $approvalService->decide($approver, $baseline->id, true);
            $this->fail('A count below reserved stock must not be approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('baseline', $exception->errors());
        }

        $this->assertSame(InventoryOpeningBaseline::STATUS_SUBMITTED, $baseline->fresh()->status);
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(5.0, (float) InventoryBalance::query()->value('on_hand_qty'));
        $this->assertDatabaseCount('inventory_opening_baseline_approvals', 0);

        $approvalService->decide($approver, $baseline->id, false, 'Hitung ulang setelah reservasi diverifikasi');
        $this->assertSame(InventoryOpeningBaseline::STATUS_DRAFT, $baseline->fresh()->status);
        $this->assertNull($line->fresh()->physical_qty);
        $this->assertDatabaseHas('inventory_opening_baseline_approvals', [
            'baseline_id' => $baseline->id,
            'decision' => 'returned',
            'decision_note' => 'Hitung ulang setelah reservasi diverifikasi',
        ]);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_later_reserved_conflict_rolls_back_earlier_variance_adjustments(): void
    {
        $submitter = $this->baselineManager();
        $this->classifyPolicies($submitter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $firstItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $secondItem = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        InventoryMovement::query()->create([
            'movement_type' => 'RECEIPT',
            'reference_type' => 'MANUAL',
            'item_id' => $secondItem->id,
            'to_location_id' => $location->id,
            'qty' => 4,
            'uom' => $secondItem->uom,
            'performed_by' => $submitter->id,
            'performed_at' => now(),
        ]);
        $this->createInventoryBalanceWithReservedFixture([
            'item_id' => $secondItem->id,
            'location_id' => $location->id,
            'on_hand_qty' => 4,
            'reserved_qty' => 3,
        ]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($submitter);
        foreach ($baseline->lines as $line) {
            $qty = $line->item_id === $firstItem->id ? 6 : 2;
            app(InventoryOpeningBaselineService::class)->capture($submitter, $baseline->id, $line->id, $qty);
        }
        $initialMovementCount = InventoryMovement::query()->count();
        $initialBalances = InventoryBalance::query()->orderBy('id')->get()->toArray();
        $approval = app(InventoryOpeningBaselineApprovalService::class);
        $approval->submit($submitter, $baseline->id);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');

        try {
            $approval->decide($approver, $baseline->id, true);
            $this->fail('Any tuple reserved-stock conflict must roll back the entire batch.');
        } catch (ValidationException) {
            $this->assertSame($initialMovementCount, InventoryMovement::query()->count());
            $this->assertSame($initialBalances, InventoryBalance::query()->orderBy('id')->get()->toArray());
            $this->assertDatabaseCount('inventory_opening_baseline_approvals', 0);
        }
    }

    public function test_readiness_requires_two_distinct_role_signoffs_and_emergency_disable_is_audited(): void
    {
        $submitter = $this->baselineManager();
        $this->classifyPolicies($submitter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($submitter);
        $line = $baseline->lines()->where('item_id', $item->id)->where('location_id', $location->id)->firstOrFail();
        app(InventoryOpeningBaselineService::class)->capture($submitter, $baseline->id, $line->id, 0);
        $approval = app(InventoryOpeningBaselineApprovalService::class);
        $approval->submit($submitter, $baseline->id);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $approval->decide($approver, $baseline->id, true);

        $modeManager = $this->userWith('inventori.view', 'inventori.mutation.activate');
        $modeService = app(InventoryMutationModeService::class);
        try {
            $modeService->activateRoutineMutations($modeManager, 'Belum lengkap');
            $this->fail('Incomplete readiness evidence must fail closed.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('Persyaratan kesiapan belum lengkap', $exception->getMessage());
        }

        $inventoryAdmin = $this->userWith('inventori.view', 'inventori.readiness.inventory-admin.accept');
        $systemOwner = $this->userWith('inventori.view', 'inventori.readiness.system-owner.accept');
        $readiness = app(InventoryReadinessService::class);
        foreach (array_keys(InventoryReadinessEvidence::CATEGORIES) as $category) {
            $reference = 'READINESS-'.$category.'-2026-01';
            $readiness->recordEvidence($inventoryAdmin, $category, $reference, 'inventory_administrator');
            try {
                $readiness->recordEvidence($inventoryAdmin, $category, $reference, 'system_owner');
                $this->fail('One user must not fill both readiness sign-off roles.');
            } catch (AuthorizationException) {
                $this->assertSame(1, $readiness->overview()['items'][$category]['evidence']->signoffs->count());
            }
            $readiness->recordEvidence($systemOwner, $category, $reference, 'system_owner');
        }

        $this->assertSame([], $readiness->missingCategories());
        $modeService->activateRoutineMutations($modeManager, 'Bukti readiness lengkap');
        $this->assertSame(InventoryMutationMode::ACTIVE, InventoryMutationMode::query()->findOrFail(1)->mode);
        $this->assertDatabaseHas('inventory_mutation_mode_events', [
            'actor_user_id' => $modeManager->id,
            'previous_mode' => InventoryMutationMode::OPENING_BASELINE,
            'new_mode' => InventoryMutationMode::ACTIVE,
            'evidence_reference' => 'baseline:'.$baseline->id.';readiness:'.app(InventoryReadinessService::class)->evidenceFingerprint(),
        ]);

        $emergencyOperator = $this->userWith('inventori.view', 'inventori.mutation.disable');
        $this->assertTrue($modeService->emergencyDisable($emergencyOperator, 'Insiden stok harus dihentikan'));
        $this->assertSame(InventoryMutationMode::DISABLED, InventoryMutationMode::query()->findOrFail(1)->mode);
        $this->assertDatabaseHas('inventory_mutation_mode_events', [
            'actor_user_id' => $emergencyOperator->id,
            'previous_mode' => InventoryMutationMode::ACTIVE,
            'new_mode' => InventoryMutationMode::DISABLED,
            'reason' => 'Insiden stok harus dihentikan',
        ]);
    }

    public function test_source_change_marks_submitted_baseline_stale_and_does_not_create_approval(): void
    {
        [$submitter, $location, , $baseline] = $this->draftBaseline(0);
        $approval = app(InventoryOpeningBaselineApprovalService::class);
        $approval->submit($submitter, $baseline->id);
        $location->update(['name' => 'Lokasi Berubah Setelah Diajukan']);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');

        try {
            $approval->decide($approver, $baseline->id, true);
            $this->fail('A changed source fingerprint must reject approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('baseline', $exception->errors());
        }

        $this->assertSame(InventoryOpeningBaseline::STATUS_STALE, $baseline->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertDatabaseCount('inventory_opening_baseline_approvals', 0);
    }

    public function test_incomplete_or_blocked_baseline_cannot_be_submitted(): void
    {
        $submitter = $this->baselineManager();
        InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryItem::factory()->create(['item_type' => 'REAGENT', 'is_active' => true]);
        $blocked = app(InventoryOpeningBaselineService::class)->generate($submitter);
        $approval = app(InventoryOpeningBaselineApprovalService::class);

        try {
            $approval->submit($submitter, $blocked->id);
            $this->fail('A baseline with an unclassified policy blocker must not be submitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('baseline', $exception->errors());
            $this->assertSame(InventoryOpeningBaseline::STATUS_BLOCKED, $blocked->fresh()->status);
        }

        $this->classifyPolicies($submitter);
        $uncounted = app(InventoryOpeningBaselineService::class)->generate($submitter);
        try {
            $approval->submit($submitter, $uncounted->id);
            $this->fail('Every tuple must have a physical count before submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('baseline', $exception->errors());
            $this->assertSame(InventoryOpeningBaseline::STATUS_DRAFT, $uncounted->fresh()->status);
            $this->assertDatabaseCount('inventory_opening_baseline_approvals', 0);
        }
    }

    public function test_source_change_after_approval_marks_approved_baseline_stale_and_blocks_activation(): void
    {
        [$submitter, $location, , $baseline] = $this->draftBaseline(0);
        $approval = app(InventoryOpeningBaselineApprovalService::class);
        $approval->submit($submitter, $baseline->id);
        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $approval->decide($approver, $baseline->id, true);
        $location->update(['name' => 'Lokasi Berubah Setelah Approval']);
        $activationManager = $this->userWith('inventori.view', 'inventori.mutation.activate');

        try {
            app(InventoryMutationModeService::class)->activateRoutineMutations($activationManager, 'Rekonsiliasi belum dilakukan');
            $this->fail('A changed post-approval source snapshot must block activation.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('Data barang, lokasi, atau stok berubah', $exception->getMessage());
        }

        $this->assertSame(InventoryOpeningBaseline::STATUS_STALE, $baseline->fresh()->status);
        $this->assertSame(InventoryMutationMode::OPENING_BASELINE, InventoryMutationMode::query()->findOrFail(1)->mode);
        $this->assertDatabaseHas('inventory_opening_baseline_approvals', [
            'baseline_id' => $baseline->id,
            'decision' => 'approved',
        ]);
    }

    public function test_lot_policy_change_invalidates_its_prior_readiness_signoffs(): void
    {
        $manager = $this->baselineManager();
        $this->classifyPolicies($manager);
        $inventoryAdmin = $this->userWith('inventori.view', 'inventori.readiness.inventory-admin.accept');
        $systemOwner = $this->userWith('inventori.view', 'inventori.readiness.system-owner.accept');
        $readiness = app(InventoryReadinessService::class);
        $readiness->recordEvidence($inventoryAdmin, 'lot_item_policies', 'LOT-POLICY-APPROVAL-1', 'inventory_administrator');
        $readiness->recordEvidence($systemOwner, 'lot_item_policies', 'LOT-POLICY-APPROVAL-1', 'system_owner');
        $this->assertNotContains('lot_item_policies', $readiness->missingCategories());

        app(InventoryLotTrackingPolicyService::class)->updateMatrix($manager, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::LOT_TRACKED,
        ], 'Keputusan lot diperbarui setelah sign-off');

        $this->assertContains('lot_item_policies', $readiness->missingCategories());
        $readiness->recordEvidence($inventoryAdmin, 'lot_item_policies', 'LOT-POLICY-APPROVAL-2', 'inventory_administrator');
        $readiness->recordEvidence($systemOwner, 'lot_item_policies', 'LOT-POLICY-APPROVAL-2', 'system_owner');
        $this->assertNotContains('lot_item_policies', $readiness->missingCategories());
        $this->assertSame(2, InventoryReadinessEvidence::query()->where('category', 'lot_item_policies')->count());
    }

    public function test_permission_grant_change_invalidates_prior_readiness_signoffs_even_with_same_reference(): void
    {
        $this->baselineManager();
        $inventoryAdmin = $this->userWith('inventori.view', 'inventori.readiness.inventory-admin.accept');
        $systemOwner = $this->userWith('inventori.view', 'inventori.readiness.system-owner.accept');
        $readiness = app(InventoryReadinessService::class);
        $readiness->recordEvidence($inventoryAdmin, 'permission_grants', 'PERMISSION-ROSTER-APPROVAL-1', 'inventory_administrator');
        $readiness->recordEvidence($systemOwner, 'permission_grants', 'PERMISSION-ROSTER-APPROVAL-1', 'system_owner');
        $this->assertNotContains('permission_grants', $readiness->missingCategories());

        $operator = User::factory()->create(['role' => 'penyelia']);
        $operator->grantPermission('inventori.receipt.create');
        $this->assertContains('permission_grants', $readiness->missingCategories());

        $readiness->recordEvidence($inventoryAdmin, 'permission_grants', 'PERMISSION-ROSTER-APPROVAL-1', 'inventory_administrator');
        $readiness->recordEvidence($systemOwner, 'permission_grants', 'PERMISSION-ROSTER-APPROVAL-1', 'system_owner');
        $this->assertNotContains('permission_grants', $readiness->missingCategories());
        $this->assertSame(2, InventoryReadinessEvidence::query()->where('category', 'permission_grants')->count());
    }

    public function test_baseline_submission_approval_and_readiness_pages_are_authorized_http_workflows(): void
    {
        [$submitter, , , $baseline] = $this->draftBaseline(0);
        $submitResponse = $this->actingAs($submitter)
            ->post(route('inventory.opening-baseline.submit', $baseline))
            ->assertRedirect(route('inventory.opening-baseline.show', $baseline));
        $submitResponse->assertSessionHas('success');

        $approver = $this->userWith('inventori.view', 'inventori.stocktake.approve');
        $this->actingAs($approver)
            ->get(route('inventory.opening-baseline.index'))
            ->assertOk()
            ->assertSee('Menunggu pemeriksaan');
        $this->get(route('inventory.opening-baseline.show', $baseline))
            ->assertOk()
            ->assertSee('Pemeriksaan oleh petugas lain')
            ->assertSee('Setujui dan catat selisih');
        $this->post(route('inventory.opening-baseline.decision', $baseline), ['decision' => 'approve'])
            ->assertRedirect(route('inventory.opening-baseline.show', $baseline))
            ->assertSessionHas('success');

        $inventoryAdmin = $this->userWith('inventori.view', 'inventori.readiness.inventory-admin.accept');
        $this->actingAs($inventoryAdmin)
            ->get(route('inventory.opening-baseline.readiness'))
            ->assertOk()
            ->assertSee('Bukti Kesiapan Operasi Inventori')
            ->assertSee('Penetapan izin dan akses lokasi');
        $this->post(route('inventory.opening-baseline.readiness.evidence'), [
            'category' => 'permission_grants',
            'signoff_role' => 'inventory_administrator',
            'evidence_reference' => 'READINESS-PERM-001',
        ])->assertRedirect(route('inventory.opening-baseline.index'))
            ->assertSessionHas('success');
    }

    private function draftBaseline(float $physicalQty): array
    {
        $submitter = $this->baselineManager();
        $this->classifyPolicies($submitter);
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        $item = InventoryItem::factory()->create(['item_type' => 'CONSUMABLE', 'is_active' => true]);
        $baseline = app(InventoryOpeningBaselineService::class)->generate($submitter);
        $line = $baseline->lines()->where('item_id', $item->id)->where('location_id', $location->id)->firstOrFail();
        foreach ($baseline->lines as $baselineLine) {
            app(InventoryOpeningBaselineService::class)->capture(
                $submitter,
                $baseline->id,
                $baselineLine->id,
                $baselineLine->item_id === $item->id ? $physicalQty : (float) $baselineLine->system_on_hand_qty,
            );
        }

        return [$submitter, $location, $item, $baseline, $line];
    }

    private function baselineManager(): User
    {
        $manager = $this->userWith('inventori.view', 'inventori.stocktake.submit', 'inventori.location-access.manage');
        app(\App\Services\InventoryMutationModeService::class)->beginOpeningBaseline($manager, 'Tes approval baseline');

        return $manager;
    }

    private function classifyPolicies(User $manager): void
    {
        app(InventoryLotTrackingPolicyService::class)->updateMatrix($manager, [
            'REAGENT' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONSUMABLE' => InventoryLotTrackingPolicy::UNTRACKED,
            'STANDARD' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'CONTROL' => InventoryLotTrackingPolicy::LOT_TRACKED,
            'OTHER' => InventoryLotTrackingPolicy::UNTRACKED,
        ], 'Persetujuan policy untuk test approval');
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
