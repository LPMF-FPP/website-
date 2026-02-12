<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Cache key prefix untuk permission.
     */
    private const CACHE_PREFIX = 'user_permissions_';

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
            'kaji-ulang' => 'Kaji Ulang Permintaan',
            'pengujian' => 'Pengujian',
            'penyerahan' => 'Penyerahan',
            'tracking' => 'Tracking',
            'pencarian' => 'Pencarian',
            'statistik' => 'Statistik',
            'monitoring' => 'Monitoring Suhu',
            'inventori' => 'Inventori',
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
    public function syncUserPermissions(User $user, array $permissions): void
    {
        // Get role defaults untuk perbandingan
        $roleDefaults = RolePermission::where('role', $user->role)
            ->pluck('permission_id')
            ->toArray();

        foreach ($permissions as $permissionId => $granted) {
            $isRoleDefault = in_array($permissionId, $roleDefaults);

            // Jika sama dengan role default, hapus custom (tidak perlu override)
            if ($granted === $isRoleDefault) {
                UserPermission::where('user_id', $user->id)
                    ->where('permission_id', $permissionId)
                    ->delete();
            } else {
                // Simpan sebagai custom override
                UserPermission::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'permission_id' => $permissionId,
                    ],
                    [
                        'granted' => $granted,
                    ]
                );
            }
        }

        // Clear cache
        $this->clearUserCache($user);
    }

    /**
     * Reset semua permission user ke default role.
     */
    public function resetToRoleDefaults(User $user): void
    {
        // Hapus semua custom permission
        UserPermission::where('user_id', $user->id)->delete();

        // Clear cache
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
            'inventori' => ['view', 'create', 'edit', 'delete'],
            'changelogs' => ['view'],
            'analysts' => ['view', 'create', 'edit', 'delete'],
            'settings' => ['view', 'edit'],
            'qmh' => ['view', 'create'],
        ];
    }

    /**
     * Get action display name.
     */
    public function getActionDisplayName(string $action): string
    {
        $names = [
            'view' => 'Lihat',
            'create' => 'Tambah',
            'edit' => 'Edit',
            'delete' => 'Hapus',
            'export' => 'Export',
        ];

        return $names[$action] ?? ucfirst($action);
    }
}
