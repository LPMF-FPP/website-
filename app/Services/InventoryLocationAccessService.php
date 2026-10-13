<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLocationAccess;
use App\Models\InventoryLocationAccessEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use LogicException;

class InventoryLocationAccessService
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function locationsForView(Builder|Relation $query, User $user): Builder|Relation
    {
        return $query->where(function (Builder $locations) use ($user): void {
            $locations->where('is_restricted', false)
                ->orWhereExists(function ($grants) use ($user): void {
                    $grants->selectRaw('1')
                        ->from('inventory_location_access as location_access')
                        ->whereColumn('location_access.location_id', 'inventory_locations.id')
                        ->where('location_access.user_id', $user->id)
                        ->where('location_access.can_view', true);
                });
        });
    }

    public function locationsForTransact(Builder|Relation $query, User $user): Builder|Relation
    {
        return $query->where(function (Builder $locations) use ($user): void {
            $locations->where('is_restricted', false)
                ->orWhereExists(function ($grants) use ($user): void {
                    $grants->selectRaw('1')
                        ->from('inventory_location_access as location_access')
                        ->whereColumn('location_access.location_id', 'inventory_locations.id')
                        ->where('location_access.user_id', $user->id)
                        ->where('location_access.can_transact', true);
                });
        });
    }

    public function canViewLocation(User $user, InventoryLocation $location): bool
    {
        return ! $location->is_restricted || InventoryLocationAccess::query()
            ->where('user_id', $user->id)
            ->where('location_id', $location->id)
            ->where('can_view', true)
            ->exists();
    }

    public function authorizeLockedView(User $user, InventoryLocation $location): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Restricted-location view checks require an active database transaction.');
        }

        if (! $location->is_restricted) {
            return;
        }

        $grant = InventoryLocationAccess::query()
            ->where('user_id', $user->id)
            ->where('location_id', $location->id)
            ->lockForUpdate()
            ->first();

        if (! $grant?->can_view) {
            throw new AuthorizationException('Akses lihat ke lokasi ini tidak diberikan.');
        }
    }

    public function canTransactLocation(User $user, InventoryLocation $location): bool
    {
        return ! $location->is_restricted || InventoryLocationAccess::query()
            ->where('user_id', $user->id)
            ->where('location_id', $location->id)
            ->where('can_transact', true)
            ->exists();
    }

    public function visibleLocationIds(User $user): Builder
    {
        return $this->locationsForView(InventoryLocation::query(), $user)->select('id');
    }

    public function transactLocationIds(User $user): Builder
    {
        return $this->locationsForTransact(InventoryLocation::query(), $user)->select('id');
    }

    public function itemHasUnviewableStockLocation(User $user, int $itemId): bool
    {
        return InventoryBalance::query()
            ->where('item_id', $itemId)
            ->where(function ($balances): void {
                $balances->where('on_hand_qty', '>', 0)->orWhere('reserved_qty', '>', 0);
            })
            ->whereHas('location', fn ($locations) => $locations->where('is_restricted', true))
            ->whereNotIn('location_id', $this->visibleLocationIds($user))
            ->exists();
    }

    public function lotsForView(Builder $query, User $user): Builder
    {
        $visibleLocationIds = $this->visibleLocationIds($user);

        return $query->whereHas('item', fn (Builder $item) => $this->itemsForView($item, $user))
            ->where(function (Builder $lots) use ($visibleLocationIds): void {
                $lots->where(function (Builder $unassociatedLots): void {
                    $unassociatedLots->whereDoesntHave('balances')->whereDoesntHave('movements');
                })->orWhereHas('balances', fn ($balances) => $balances->whereIn('location_id', $visibleLocationIds))
                    ->orWhereHas('movements', function ($movements) use ($visibleLocationIds): void {
                        $movements->whereIn('from_location_id', $visibleLocationIds)
                            ->orWhereIn('to_location_id', $visibleLocationIds);
                    });
            });
    }

    public function itemsForView(Builder $query, User $user): Builder
    {
        $visibleLocationIds = $this->visibleLocationIds($user);

        return $query->where(function (Builder $items) use ($visibleLocationIds): void {
            $items->where(function (Builder $unassociatedItems): void {
                $unassociatedItems->whereDoesntHave('balances')->whereDoesntHave('movements');
            })->orWhereHas('balances', fn ($balances) => $balances->whereIn('location_id', $visibleLocationIds))
                ->orWhereHas('movements', function ($movements) use ($visibleLocationIds): void {
                    $movements->where(function ($endpoints) use ($visibleLocationIds): void {
                        $endpoints->whereNull('from_location_id')->orWhereIn('from_location_id', $visibleLocationIds);
                    })->where(function ($endpoints) use ($visibleLocationIds): void {
                        $endpoints->whereNull('to_location_id')->orWhereIn('to_location_id', $visibleLocationIds);
                    })->where(function ($endpoints): void {
                        $endpoints->whereNotNull('from_location_id')->orWhereNotNull('to_location_id');
                    });
                });
        });
    }

    public function itemsForTransaction(Builder $query, User $user, bool $includeUnassociated = false): Builder
    {
        $transactionLocationIds = $this->transactLocationIds($user);

        return $query->where(function (Builder $items) use ($transactionLocationIds, $includeUnassociated): void {
            if ($includeUnassociated) {
                $items->where(function (Builder $unassociatedItems): void {
                    $unassociatedItems->whereDoesntHave('balances')->whereDoesntHave('movements');
                })->orWhereHas('movements', function (Builder $movements) use ($transactionLocationIds): void {
                    $movements->where(function (Builder $endpoints) use ($transactionLocationIds): void {
                        $endpoints->whereIn('from_location_id', $transactionLocationIds)
                            ->orWhereIn('to_location_id', $transactionLocationIds);
                    });
                });
            }

            $items->orWhereHas('balances', fn ($balances) => $balances->whereIn('location_id', $transactionLocationIds));
        });
    }

    public function itemHasTransactionAccess(User $user, int $itemId, ?int $locationId, bool $allowUnassociated = false): bool
    {
        $transactLocationIds = $this->transactLocationIds($user);

        return InventoryItem::query()->active()->whereKey($itemId)
            ->where(function (Builder $items) use ($locationId, $transactLocationIds, $allowUnassociated): void {
                if ($allowUnassociated) {
                    $items->where(function (Builder $unassociated): void {
                        $unassociated->whereDoesntHave('balances')->whereDoesntHave('movements');
                    })->orWhereHas('movements', function (Builder $movements) use ($locationId, $transactLocationIds): void {
                        $movements->where(function (Builder $endpoints) use ($transactLocationIds): void {
                            $endpoints->whereIn('from_location_id', $transactLocationIds)
                                ->orWhereIn('to_location_id', $transactLocationIds);
                        })->when($locationId !== null, fn (Builder $query) => $query->where(function (Builder $endpoints) use ($locationId): void {
                            $endpoints->where('from_location_id', $locationId)
                                ->orWhere('to_location_id', $locationId);
                        }));
                    });
                }

                $items->orWhereHas('balances', fn ($balances) => $balances
                    ->whereIn('location_id', $transactLocationIds)
                    ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId)));
            })->exists();
    }

    /** Authorize a mutation after the caller has locked the location parent rows. */
    public function authorizeLockedTransaction(User $actor, array $locationIds): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Restricted-location transaction checks require an active database transaction.');
        }

        $orderedLocationIds = array_values(array_unique(array_map('intval', $locationIds)));
        sort($orderedLocationIds);
        foreach ($orderedLocationIds as $locationId) {
            $location = InventoryLocation::query()->whereKey($locationId)->first();
            if (! $location) {
                throw new AuthorizationException('Lokasi inventori tidak ditemukan.');
            }

            if (! $location->is_restricted) {
                continue;
            }

            $grant = InventoryLocationAccess::query()
                ->where('user_id', $actor->id)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if (! $grant?->can_transact) {
                throw new AuthorizationException('Akses transaksi ke lokasi terbatas tidak diberikan.');
            }
        }
    }

    public function managementData(User $actor, int $locationId): array
    {
        return DB::transaction(function () use ($actor, $locationId): array {
            $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
            if (! $lockedActor || ! $lockedActor->is_active || ! $this->permissionService->userCanForMutation($lockedActor, 'inventori.location-access.manage')) {
                throw new AuthorizationException('Anda tidak memiliki izin mengelola akses lokasi.');
            }

            $location = InventoryLocation::query()->whereKey($locationId)->lockForUpdate()->first();
            if (! $location || ! $location->is_restricted) {
                throw new AuthorizationException('Pengelolaan akses hanya tersedia untuk lokasi terbatas.');
            }

            $users = User::query()->where(function ($users) use ($location): void {
                $users->where('is_active', true)->orWhereExists(function ($grants) use ($location): void {
                    $grants->selectRaw('1')->from('inventory_location_access')
                        ->whereColumn('user_id', 'users.id')->where('location_id', $location->id)
                        ->where(fn ($rights) => $rights->where('can_view', true)->orWhere('can_transact', true));
                });
            })->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();

            return [
                'location' => $location,
                'users' => $users,
                'grants' => InventoryLocationAccess::query()
                    ->where('location_id', $location->id)
                    ->whereIn('user_id', $users->getCollection()->pluck('id'))
                    ->get()
                    ->keyBy('user_id'),
                'events' => InventoryLocationAccessEvent::query()->where('location_id', $location->id)
                    ->with(['actor:id,name', 'target:id,name'])->latest('occurred_at')->paginate(100)->withQueryString(),
            ];
        });
    }

    public function changeGrant(
        User $actor,
        int $targetUserId,
        int $locationId,
        bool $canView,
        bool $canTransact,
        ?string $reason = null,
        ?array $context = null,
    ): ?InventoryLocationAccess {
        $result = DB::transaction(function () use ($actor, $targetUserId, $locationId, $canView, $canTransact, $reason, $context): ?InventoryLocationAccess {
            [$target, $location] = $this->lockAndAuthorizeManager($actor, $targetUserId, $locationId);
            $grant = InventoryLocationAccess::query()
                ->where('user_id', $target->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            $previousView = (bool) $grant?->can_view;
            $previousTransact = (bool) $grant?->can_transact;
            if (! $target->is_active && (($canView && ! $previousView) || ($canTransact && ! $previousTransact))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['user_id' => 'Pengguna nonaktif hanya dapat dikurangi atau dicabut hak aksesnya.']);
            }
            if ($previousView === $canView && $previousTransact === $canTransact) {
                return $grant;
            }

            if ($grant) {
                $grant->update(['can_view' => $canView, 'can_transact' => $canTransact]);
            } elseif ($canView || $canTransact) {
                $grant = InventoryLocationAccess::query()->create([
                    'user_id' => $target->id,
                    'location_id' => $location->id,
                    'can_view' => $canView,
                    'can_transact' => $canTransact,
                ]);
            }

            $removedRight = ($previousView && ! $canView) || ($previousTransact && ! $canTransact);
            $addedRight = (! $previousView && $canView) || (! $previousTransact && $canTransact);

            InventoryLocationAccessEvent::query()->create([
                'actor_user_id' => $actor->id,
                'target_user_id' => $target->id,
                'location_id' => $location->id,
                'previous_can_view' => $previousView,
                'previous_can_transact' => $previousTransact,
                'can_view' => $canView,
                'can_transact' => $canTransact,
                'action' => $removedRight && $addedRight ? 'updated' : ($removedRight ? 'revoked' : 'granted'),
                'reason' => $reason,
                'context' => $context,
                'occurred_at' => now(),
            ]);

            return $grant;
        });

        DB::afterCommit(function () use ($targetUserId): void {
            $target = new User;
            $target->id = $targetUserId;
            $this->permissionService->clearUserCache($target);
        });

        return $result;
    }

    /** @return array{0: User, 1: InventoryLocation} */
    private function lockAndAuthorizeManager(User $actor, int $targetUserId, int $locationId): array
    {
        $authenticatedActor = auth()->user();
        if ($authenticatedActor && $authenticatedActor->id !== $actor->id) {
            throw new AuthorizationException('Aktor pengelola akses lokasi tidak sesuai dengan pengguna yang terautentikasi.');
        }

        $users = User::query()
            ->whereIn('id', collect([$actor->id, $targetUserId])->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $lockedActor = $users->get($actor->id);
        $target = $users->get($targetUserId);
        if (! $lockedActor || ! $lockedActor->is_active || ! $target) {
            throw new AuthorizationException('Pengguna tidak berwenang mengelola akses lokasi.');
        }

        if (! $this->permissionService->userCanForMutation($lockedActor, 'inventori.location-access.manage')) {
            throw new AuthorizationException('Anda tidak memiliki izin mengelola akses lokasi.');
        }

        $location = InventoryLocation::query()
            ->whereKey($locationId)
            ->lockForUpdate()
            ->first();
        if (! $location || ! $location->is_restricted) {
            throw new AuthorizationException('Pengelolaan akses hanya tersedia untuk lokasi terbatas.');
        }

        return [$target, $location];
    }
}
