<?php

namespace App\Services;

use App\Models\InventoryMutationMode;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InventoryMutationGate
{
    public function __construct(private readonly PermissionService $permissionService) {}

    /**
     * Authorize a stock mutation while holding the gate and permission locks
     * until the caller's surrounding transaction commits.
     *
     * @throws AuthorizationException
     */
    public function authorize(?int $actorId, string|array $permissionNames): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Inventory mutations must be authorized inside a database transaction.');
        }

        try {
            $mode = InventoryMutationMode::query()
                ->whereKey(InventoryMutationMode::SINGLETON_ID)
                ->sharedLock()
                ->value('mode');
        } catch (QueryException $exception) {
            throw new AuthorizationException('Mutasi inventori sedang dinonaktifkan.', previous: $exception);
        }

        if ($mode !== InventoryMutationMode::ACTIVE) {
            throw new AuthorizationException('Mutasi inventori sedang dinonaktifkan.');
        }

        if ($actorId === null) {
            throw new AuthorizationException('Aktor mutasi inventori tidak dapat diverifikasi.');
        }

        $actor = User::query()
            ->whereKey($actorId)
            ->lockForUpdate()
            ->first();

        $permissionNames = is_array($permissionNames) ? $permissionNames : [$permissionNames];

        if (! $actor || ! $actor->is_active || ! $this->permissionService->userHasPermissionsForMutation($actor, $permissionNames)) {
            throw new AuthorizationException('Aktor tidak memiliki izin untuk mutasi inventori ini.');
        }
    }
}
