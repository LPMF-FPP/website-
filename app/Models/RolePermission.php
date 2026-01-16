<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'permission_id',
    ];

    /**
     * Get the permission for this role permission.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Scope to filter by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Get all permissions for a specific role.
     */
    public static function getPermissionsForRole(string $role): array
    {
        return static::byRole($role)
            ->with('permission')
            ->get()
            ->pluck('permission.name')
            ->toArray();
    }

    /**
     * Check if a role has a specific permission.
     */
    public static function roleHasPermission(string $role, string $permissionName): bool
    {
        return static::byRole($role)
            ->whereHas('permission', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }
}
