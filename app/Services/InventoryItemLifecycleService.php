<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryItemLifecycleEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryItemLifecycleService
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly InventoryLocationAccessService $locationAccess,
    ) {}

    public function archive(int $itemId, User $actor, ?string $reason = null): bool
    {
        return $this->transition($itemId, $actor, false, $reason);
    }

    public function restore(int $itemId, User $actor, ?string $reason = null): bool
    {
        return $this->transition($itemId, $actor, true, $reason);
    }

    private function transition(int $itemId, User $actor, bool $isActive, ?string $reason): bool
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor perubahan status item tidak sesuai dengan pengguna yang terautentikasi.');
        }

        return DB::transaction(function () use ($itemId, $actor, $isActive, $reason): bool {
            $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
            if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userHasPermissionsForMutation($lockedActor, [
                'inventori.view',
                'inventori.edit',
            ])) {
                throw new AuthorizationException('Aktor tidak memiliki izin mengarsipkan item.');
            }

            if (! $this->locationAccess->itemsForView(InventoryItem::query(), $lockedActor)->whereKey($itemId)->exists()) {
                throw new AuthorizationException('Item tidak tersedia dalam cakupan akses Anda.');
            }

            $item = InventoryItem::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($item->is_active === $isActive) {
                return false;
            }

            if (! $isActive && $this->locationAccess->itemHasUnviewableStockLocation($lockedActor, $item->id)) {
                throw new AuthorizationException('Akses ke seluruh lokasi dengan stok item ini diperlukan sebelum pengarsipan.');
            }

            if (! $isActive && InventoryBalance::query()->where('item_id', $item->id)->where(function ($balances): void {
                $balances->where('on_hand_qty', '>', 0)->orWhere('reserved_qty', '>', 0);
            })->exists()) {
                throw ValidationException::withMessages([
                    'archive' => 'Item masih memiliki saldo. Selesaikan saldo on-hand dan reserved sebelum mengarsipkan item.',
                ]);
            }

            $previousIsActive = (bool) $item->is_active;
            $item->is_active = $isActive;
            $item->save();

            InventoryItemLifecycleEvent::query()->create([
                'inventory_item_id' => $item->id,
                'actor_user_id' => $actor->id,
                'previous_is_active' => $previousIsActive,
                'is_active' => $isActive,
                'event_type' => $isActive ? 'restored' : 'archived',
                'reason' => $reason !== null ? trim($reason) : null,
                'occurred_at' => now(),
            ]);

            return true;
        });
    }
}
