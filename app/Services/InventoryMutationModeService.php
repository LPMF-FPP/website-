<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryMutationMode;
use App\Models\InventoryMutationModeEvent;
use App\Models\InventoryOpeningBaseline;
use App\Models\InventoryOpeningBaselineApproval;
use App\Models\InventoryReadinessEvidence;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class InventoryMutationModeService
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly InventoryReadinessService $readinessService,
        private readonly InventoryOpeningBaselineService $baselineService,
    ) {}

    public function activateRoutineMutations(User $actor, ?string $reason = null): void
    {
        $this->assertAuthenticatedActor($actor);

        $activated = DB::transaction(function () use ($actor, $reason): bool {
            $mode = InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->lockForUpdate()->first();
            if (! $mode || $mode->mode !== InventoryMutationMode::OPENING_BASELINE) {
                throw new AuthorizationException('Perubahan stok rutin hanya dapat diaktifkan saat pencatatan stok awal sedang berlangsung.');
            }
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE users, permissions, role_permissions, user_permissions, inventory_location_access, inventory_locations, inventory_items, inventory_lots, inventory_balances, inventory_movements, inventory_lot_tracking_policies IN SHARE MODE');
            }

            $approvedBaseline = InventoryOpeningBaseline::query()
                ->where('status', InventoryOpeningBaseline::STATUS_APPROVED)
                ->whereHas('approvals', fn ($approvals) => $approvals->where('decision', 'approved'))
                ->orderByDesc('approved_at')
                ->first();
            if (! $approvedBaseline) {
                throw new AuthorizationException('Belum ada catatan stok awal yang disetujui.');
            }

            $users = User::query()->whereIn('id', collect([$actor->id, $approvedBaseline->generated_by])->unique()->sort()->values())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedActor = $users->get($actor->id);
            $manifestOwner = $users->get($approvedBaseline->generated_by);
            if (! $lockedActor?->is_active || ! $manifestOwner
                || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, ['inventori.view', 'inventori.mutation.activate'])) {
                throw new AuthorizationException('Anda tidak memiliki izin mengaktifkan mutasi inventori.');
            }

            $approvedBaseline = InventoryOpeningBaseline::query()->whereKey($approvedBaseline->id)->lockForUpdate()->firstOrFail();
            if ($approvedBaseline->status !== InventoryOpeningBaseline::STATUS_APPROVED) {
                throw new AuthorizationException('Data stok setelah persetujuan tidak tersedia.');
            }
            $approvedDecision = InventoryOpeningBaselineApproval::query()
                ->where('baseline_id', $approvedBaseline->id)
                ->where('decision', 'approved')
                ->orderByDesc('submission_revision')
                ->lockForUpdate()
                ->first();
            if (! $approvedDecision?->resulting_source_fingerprint) {
                throw new AuthorizationException('Catatan jumlah stok setelah persetujuan tidak tersedia.');
            }
            $approvedSource = $this->baselineService->approvalSnapshotFor($manifestOwner);
            if ($approvedSource['blockers'] !== []
                || ! hash_equals($approvedDecision->resulting_source_fingerprint, $approvedSource['fingerprint'])) {
                $approvedBaseline->status = InventoryOpeningBaseline::STATUS_STALE;
                $approvedBaseline->stale_at = now();
                $approvedBaseline->blockers = [...($approvedBaseline->blockers ?? []), 'source_changed_after_approval'];
                $approvedBaseline->save();

                return false;
            }

            $missing = $this->readinessService->missingCategories();
            if ($missing !== []) {
                $labels = collect(InventoryReadinessEvidence::CATEGORIES);
                $missingLabels = collect($missing)->map(fn (string $category): string => $labels[$category] ?? 'persyaratan lain')->join(', ');
                throw new AuthorizationException('Persyaratan kesiapan belum lengkap: '.$missingLabels.'.');
            }

            $previousMode = $mode->mode;
            $mode->mode = InventoryMutationMode::ACTIVE;
            $mode->save();
            InventoryMutationModeEvent::query()->create([
                'actor_user_id' => $lockedActor->id,
                'previous_mode' => $previousMode,
                'new_mode' => $mode->mode,
                'reason' => $reason !== null ? trim($reason) : null,
                'evidence_reference' => 'baseline:'.$approvedBaseline->id.';readiness:'.$this->readinessService->evidenceFingerprint(),
                'occurred_at' => now(),
            ]);

            return true;
        });

        if (! $activated) {
            throw new AuthorizationException('Data barang, lokasi, atau stok berubah setelah disetujui. Buat dan periksa ulang catatan stok awal sebelum mengaktifkan perubahan.');
        }
    }

    public function emergencyDisable(User $actor, string $reason): bool
    {
        $this->assertAuthenticatedActor($actor);
        $reason = trim($reason);
        if ($reason === '') {
            throw new AuthorizationException('Tuliskan alasan untuk mematikan perubahan stok.');
        }

        return DB::transaction(function () use ($actor, $reason): bool {
            $mode = InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->lockForUpdate()->first();
            if (! $mode) {
                throw new AuthorizationException('Mode mutasi tidak tersedia; operasi tetap dinonaktifkan.');
            }
            $lockedActor = $this->lockAuthorizedActor($actor, ['inventori.view', 'inventori.mutation.disable']);
            if ($mode->mode === InventoryMutationMode::DISABLED) {
                return false;
            }

            $previousMode = $mode->mode;
            $mode->mode = InventoryMutationMode::DISABLED;
            $mode->save();
            InventoryMutationModeEvent::query()->create([
                'actor_user_id' => $lockedActor->id,
                'previous_mode' => $previousMode,
                'new_mode' => InventoryMutationMode::DISABLED,
                'reason' => $reason,
                'evidence_reference' => null,
                'occurred_at' => now(),
            ]);

            return true;
        });
    }

    public function beginOpeningBaseline(User $actor, ?string $reason = null): bool
    {
        $this->assertAuthenticatedActor($actor);

        return DB::transaction(function () use ($actor, $reason): bool {
            $mode = InventoryMutationMode::query()->whereKey(InventoryMutationMode::SINGLETON_ID)->lockForUpdate()->first();
            if (! $mode) {
                throw new AuthorizationException('Mode mutasi tidak tersedia; operasi tetap dinonaktifkan.');
            }

            $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
            if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, [
                'inventori.view',
                'inventori.stocktake.submit',
                'inventori.location-access.manage',
            ])) {
                throw new AuthorizationException('Anda tidak memiliki izin untuk memulai pencatatan stok awal.');
            }

            if ($mode->mode === InventoryMutationMode::OPENING_BASELINE) {
                return false;
            }
            if ($mode->mode !== InventoryMutationMode::DISABLED) {
                throw new AuthorizationException('Pencatatan stok awal hanya dapat dimulai saat perubahan stok sedang dimatikan.');
            }

            $previousMode = $mode->mode;
            $mode->mode = InventoryMutationMode::OPENING_BASELINE;
            $mode->save();

            InventoryMutationModeEvent::query()->create([
                'actor_user_id' => $lockedActor->id,
                'previous_mode' => $previousMode,
                'new_mode' => $mode->mode,
                'reason' => $reason !== null ? trim($reason) : null,
                'evidence_reference' => null,
                'occurred_at' => now(),
            ]);

            return true;
        });
    }

    private function lockAuthorizedActor(User $actor, array $permissions): User
    {
        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, $permissions)) {
            throw new AuthorizationException('Anda tidak memiliki izin mengubah mode mutasi inventori.');
        }

        return $lockedActor;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor perubahan mode inventori tidak sesuai dengan pengguna yang terautentikasi.');
        }
    }
}
