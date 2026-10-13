<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Cache key prefix untuk permission.
     */
    private const CACHE_PREFIX = 'user_permissions_v2_';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Cek apakah user punya permission tertentu.
     */
    public function userCan(User $user, string $permissionName): bool
    {
        $permissions = $this->getUserPermissions($user);

        // Cek apakah permission ada dalam list
        $permission = $permissions->firstWhere('name', $permissionName);

        if (! $permission) {
            return false;
        }

        return $permission['has_access'] === true;
    }

    /**
     * Resolve a mutation permission from persisted state while the caller holds
     * the actor lock and an active transaction.
     */
    public function userCanForMutation(User $user, string $permissionName): bool
    {
        return $this->userHasPermissionsForMutation($user, [$permissionName]);
    }

    /**
     * Resolve all requested mutation permissions from persisted state under
     * the AD-13 permission/role/override lock order.
     *
     * @param  list<string>  $permissionNames
     */
    public function userHasPermissionsForMutation(User $user, array $permissionNames): bool
    {
        $permissionNames = collect($permissionNames)->unique()->values();
        if ($permissionNames->isEmpty()) {
            return false;
        }

        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('name');

        if ($permissions->count() !== $permissionNames->count()) {
            return false;
        }

        $permissionIds = $permissions->pluck('id')->sort()->values();
        $rolePermissionIds = RolePermission::query()
            ->where('role', $user->role)
            ->whereIn('permission_id', $permissionIds)
            ->orderBy('permission_id')
            ->lockForUpdate()
            ->pluck('permission_id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->all();

        $userPermissions = UserPermission::query()
            ->where('user_id', $user->id)
            ->whereIn('permission_id', $permissionIds)
            ->orderBy('permission_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('permission_id');

        foreach ($permissions as $permission) {
            $userPermission = $userPermissions->get($permission->id);
            $hasAccess = $userPermission
                ? (bool) $userPermission->granted
                : in_array((int) $permission->id, $rolePermissionIds, true);

            if (! $hasAccess) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get semua permission user dengan status (granted/revoked/default).
     */
    public function getUserPermissions(User $user): Collection
    {
        $cacheKey = self::CACHE_PREFIX.$user->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $this->buildUserPermissions($user);
        });
    }

    /**
     * Build permission list untuk user.
     */
    private function buildUserPermissions(User $user): Collection
    {
        // Get semua permission
        $allPermissions = Permission::all();

        // Get role default permissions
        $rolePermissions = RolePermission::where('role', $user->role)
            ->pluck('permission_id')
            ->toArray();

        // Get user custom permissions
        $userPermissions = UserPermission::where('user_id', $user->id)
            ->get()
            ->keyBy('permission_id');

        return $allPermissions->map(function ($permission) use ($rolePermissions, $userPermissions) {
            $hasRoleDefault = in_array($permission->id, $rolePermissions);
            $userPermission = $userPermissions->get($permission->id);

            // Determine access
            $hasAccess = $hasRoleDefault;
            $isCustom = false;

            if ($userPermission) {
                $hasAccess = $userPermission->granted;
                $isCustom = true;
            }

            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'module' => $permission->module,
                'action' => $permission->action,
                'has_access' => $hasAccess,
                'is_custom' => $isCustom,
                'is_role_default' => $hasRoleDefault,
            ];
        });
    }

    /**
     * Get default permission untuk role tertentu.
     */
    public function getRolePermissions(string $role): Collection
    {
        return RolePermission::where('role', $role)
            ->with('permission')
            ->get()
            ->map(function ($rolePermission) {
                return [
                    'id' => $rolePermission->permission->id,
                    'name' => $rolePermission->permission->name,
                    'display_name' => $rolePermission->permission->display_name,
                    'module' => $rolePermission->permission->module,
                    'action' => $rolePermission->permission->action,
                ];
            });
    }

    /**
     * Get permission data grouped by module untuk UI.
     */
    public function getPermissionsForUI(User $user): array
    {
        $permissions = $this->getUserPermissions($user);

        // Group by module
        $grouped = $permissions->groupBy('module');

        // Format untuk UI
        $result = [];
        foreach ($grouped as $module => $modulePermissions) {
            $actions = [];
            foreach ($modulePermissions as $perm) {
                $actions[$perm['action']] = [
                    'id' => $perm['id'],
                    'has_access' => $perm['has_access'],
                    'is_custom' => $perm['is_custom'],
                    'is_role_default' => $perm['is_role_default'],
                ];
            }

            $result[$module] = [
                'display_name' => $this->getModuleDisplayName($module),
                'actions' => $actions,
            ];
        }

        return $result;
    }

    /**
     * Get display name untuk module.
     */
    private function getModuleDisplayName(string $module): string
    {
        $names = [
            'dashboard' => 'Dashboard',
            'permintaan' => 'Permintaan',
            'sahli' => 'Saksi Ahli',
            'kaji-ulang' => 'Kaji Ulang',
            'pengujian' => 'Pengujian',
            'penyerahan' => 'Penyerahan',
            'tracking' => 'Tracking',
            'pencarian' => 'Pencarian',
            'statistik' => 'Statistik',
            'monitoring' => 'Monitoring Suhu',
            'inventori' => 'Inventori',
            'guest-book' => 'Buku Tamu',
            'changelogs' => 'Changelogs',
            'analysts' => 'Manajemen Staff',
            'settings' => 'Pengaturan Sistem',
            'qmh' => 'Quality Management Hub',
        ];

        return $names[$module] ?? ucfirst($module);
    }

    /**
     * Simpan custom permission untuk user.
     *
     * @param  array  $permissions  Array of ['permission_id' => bool granted]
     */
    public function syncUserPermissions(User $user, array $permissions, ?User $actor = null): void
    {
        foreach ($permissions as $permissionId => $granted) {
            if (! ctype_digit((string) $permissionId) || (int) $permissionId < 1) {
                throw new \InvalidArgumentException('Permission identifiers must be positive integer IDs.');
            }

            if (! in_array($granted, [true, false, 1, 0, '1', '0'], true)) {
                throw new \InvalidArgumentException('Permission grants must be boolean values.');
            }
        }

        $permissionIds = collect(array_keys($permissions))
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->unique()
            ->sort()
            ->values();

        DB::transaction(function () use ($user, $permissions, $actor, $permissionIds): void {
            $userIds = collect([$actor?->id, $user->id])
                ->filter()
                ->map(fn ($userId): int => (int) $userId)
                ->unique()
                ->sort()
                ->values();

            $lockedUsers = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $target = $lockedUsers->get($user->id);
            if (! $target) {
                throw (new ModelNotFoundException)->setModel(User::class, [$user->id]);
            }

            $permissionRecords = Permission::query()
                ->whereIn('id', $permissionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $roleDefaults = RolePermission::query()
                ->where('role', $target->role)
                ->whereIn('permission_id', $permissionRecords->keys())
                ->orderBy('permission_id')
                ->lockForUpdate()
                ->pluck('permission_id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->all();

            $existingOverrides = UserPermission::query()
                ->where('user_id', $target->id)
                ->whereIn('permission_id', $permissionRecords->keys())
                ->orderBy('permission_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('permission_id');

            foreach ($permissions as $permissionId => $granted) {
                $permissionId = (int) $permissionId;
                if (! $permissionRecords->has($permissionId)) {
                    continue;
                }

                $isGranted = in_array($granted, [true, 1, '1'], true);
                $isRoleDefault = in_array($permissionId, $roleDefaults, true);
                $override = $existingOverrides->get($permissionId);

                if ($isGranted === $isRoleDefault) {
                    $override?->delete();

                    continue;
                }

                if ($override) {
                    $override->granted = $isGranted;
                    $override->save();
                } else {
                    UserPermission::query()->create([
                        'user_id' => $target->id,
                        'permission_id' => $permissionId,
                        'granted' => $isGranted,
                    ]);
                }
            }
        });

        $this->clearUserCache($user);
    }

    /**
     * Reset semua permission user ke default role.
     */
    public function resetToRoleDefaults(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $userIds = collect([$actor?->id, $user->id])
                ->filter()
                ->map(fn ($userId): int => (int) $userId)
                ->unique()
                ->sort()
                ->values();

            $lockedUsers = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $target = $lockedUsers->get($user->id);
            if (! $target) {
                throw (new ModelNotFoundException)->setModel(User::class, [$user->id]);
            }

            $permissionIds = RolePermission::query()
                ->where('role', $target->role)
                ->pluck('permission_id')
                ->merge(UserPermission::query()->where('user_id', $target->id)->pluck('permission_id'))
                ->unique()
                ->sort()
                ->values();

            $permissionIds = Permission::query()
                ->whereIn('id', $permissionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            RolePermission::query()
                ->where('role', $target->role)
                ->whereIn('permission_id', $permissionIds)
                ->orderBy('permission_id')
                ->lockForUpdate()
                ->get();

            UserPermission::query()
                ->where('user_id', $target->id)
                ->whereIn('permission_id', $permissionIds)
                ->orderBy('permission_id')
                ->lockForUpdate()
                ->delete();
        });

        $this->clearUserCache($user);
    }

    /**
     * Clear permission cache untuk user.
     */
    public function clearUserCache(User $user): void
    {
        Cache::forget(self::CACHE_PREFIX.$user->id);
    }

    /**
     * Get semua modules dengan available actions.
     */
    public function getAllModules(): array
    {
        return [
            'dashboard' => ['view'],
            'permintaan' => ['view', 'create', 'edit', 'delete'],
            'kaji-ulang' => ['view', 'create', 'edit', 'delete'],
            'pengujian' => ['view', 'create', 'edit', 'delete'],
            'penyerahan' => ['view', 'create', 'edit', 'delete'],
            'tracking' => ['view'],
            'pencarian' => ['view'],
            'statistik' => ['view', 'export'],
            'monitoring' => ['view'],
            'inventori' => [
                'view',
                'create',
                'edit',
                'delete',
                'receipt.create',
                'issue.create',
                'transfer.create',
                'stocktake.submit',
                'stocktake.approve',
                'material-disposal.create',
                'location-access.manage',
                'mutation.activate',
                'mutation.disable',
                'readiness.inventory-admin.accept',
                'readiness.system-owner.accept',
            ],
            'guest-book' => ['view', 'create', 'edit', 'checkout', 'delete', 'export'],
            'sample-disposal' => ['view', 'execute'],
            'changelogs' => ['view'],
            'analysts' => ['view', 'create', 'edit', 'delete'],
            'settings' => ['view', 'edit'],
            'qmh' => ['view', 'create', 'report', 'template-manage', 'unlock-force'],
        ];
    }

    /**
     * Get action display name.
     */
    public function getActionDisplayName(string $action): string
    {
        $names = [
            'view' => 'Lihat',
            'execute' => 'Eksekusi Pemusnahan Sampel',
            'create' => 'Tambah',
            'edit' => 'Edit',
            'checkout' => 'Catat Keluar',
            'delete' => 'Hapus',
            'export' => 'Export',
            'report' => 'Laporan',
            'template-manage' => 'Kelola Template',
            'unlock-force' => 'Paksa Buka Kunci',
            'receipt.create' => 'Catat Penerimaan',
            'issue.create' => 'Catat Pengeluaran',
            'transfer.create' => 'Catat Transfer',
            'stocktake.submit' => 'Ajukan Stok Opname',
            'stocktake.approve' => 'Setujui Selisih Stok Opname',
            'material-disposal.create' => 'Catat Disposal Bahan',
            'location-access.manage' => 'Kelola Akses Lokasi',
            'mutation.activate' => 'Aktifkan Mutasi Inventori',
            'mutation.disable' => 'Nonaktifkan Mutasi Inventori Darurat',
            'readiness.inventory-admin.accept' => 'Terima Bukti Kesiapan sebagai Administrator Inventori',
            'readiness.system-owner.accept' => 'Terima Bukti Kesiapan sebagai Pemilik Sistem',
        ];

        return $names[$action] ?? ucfirst($action);
    }
}
