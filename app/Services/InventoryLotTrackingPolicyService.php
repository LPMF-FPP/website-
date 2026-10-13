<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLotTrackingPolicy;
use App\Models\InventoryLotTrackingPolicyEvent;
use App\Models\InventoryMutationMode;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLotTrackingPolicyService
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function updateMatrix(User $actor, array $modesByItemType, string $evidenceReference): int
    {
        if (trim($evidenceReference) === '' || mb_strlen($evidenceReference) > 255) {
            throw ValidationException::withMessages([
                'evidence_reference' => 'Cantumkan bukti persetujuan kebijakan (maksimal 255 karakter).',
            ]);
        }

        return DB::transaction(function () use ($actor, $modesByItemType, $evidenceReference): int {
            $mode = InventoryMutationMode::query()
                ->whereKey(InventoryMutationMode::SINGLETON_ID)
                ->sharedLock()
                ->value('mode');
            if (! in_array($mode, [InventoryMutationMode::DISABLED, InventoryMutationMode::OPENING_BASELINE], true)) {
                throw new AuthorizationException('Kebijakan lot hanya dapat diubah saat inventori dinonaktifkan atau sedang menyiapkan baseline.');
            }

            $lockedActor = $this->lockAndAuthorizeActor($actor);
            $rows = InventoryLotTrackingPolicy::query()->orderBy('item_type')->lockForUpdate()->get()->keyBy('item_type');
            $updatedCount = 0;

            foreach ($modesByItemType as $itemType => $mode) {
                if (! array_key_exists($itemType, InventoryItem::ITEM_TYPES) || ! $rows->has($itemType)) {
                    throw ValidationException::withMessages([
                        'policies' => 'Satu atau lebih tipe item tidak dikenal.',
                    ]);
                }

                if ($mode !== null && ! in_array($mode, [InventoryLotTrackingPolicy::LOT_TRACKED, InventoryLotTrackingPolicy::UNTRACKED], true)) {
                    throw ValidationException::withMessages([
                        'policies.'.$itemType => 'Pilih status lot-tracked, untracked, atau belum ditetapkan.',
                    ]);
                }

                $policy = $rows->get($itemType);
                $previousMode = $policy->lot_tracking_mode;
                $normalizedEvidenceReference = trim($evidenceReference);
                if ($previousMode === $mode && $policy->evidence_reference === ($mode === null ? null : $normalizedEvidenceReference)) {
                    continue;
                }

                $policy->forceFill([
                    'lot_tracking_mode' => $mode,
                    'evidence_reference' => $mode === null ? null : $normalizedEvidenceReference,
                    'decided_by_user_id' => $mode === null ? null : $lockedActor->id,
                    'decided_at' => $mode === null ? null : now(),
                ])->save();

                InventoryLotTrackingPolicyEvent::query()->create([
                    'item_type' => $itemType,
                    'actor_user_id' => $lockedActor->id,
                    'previous_lot_tracking_mode' => $previousMode,
                    'lot_tracking_mode' => $mode,
                    'evidence_reference' => $normalizedEvidenceReference,
                    'occurred_at' => now(),
                ]);
                $updatedCount++;
            }

            return $updatedCount;
        });
    }

    private function lockAndAuthorizeActor(User $actor): User
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor matriks kebijakan inventori tidak sesuai dengan pengguna yang terautentikasi.');
        }

        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, [
            'inventori.view',
            'inventori.location-access.manage',
        ])) {
            throw new AuthorizationException('Anda tidak memiliki izin mengelola kebijakan lot inventori.');
        }

        return $lockedActor;
    }
}
