<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'module',
        'action',
    ];

    /**
     * Get the role permissions for this permission.
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Get the user permissions for this permission.
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * Get all roles that have this permission by default.
     */
    public function roles(): array
    {
        return $this->rolePermissions()->pluck('role')->toArray();
    }

    /**
     * Get all users that have custom override for this permission.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot('granted')
            ->withTimestamps();
    }

    /**
     * Scope to filter by module.
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Get modules list with their permissions grouped.
     */
    public static function getGroupedByModule(): array
    {
        return static::orderBy('module')
            ->orderByRaw("FIELD(action, 'view', 'create', 'edit', 'delete', 'export')")
            ->get()
            ->groupBy('module')
            ->toArray();
    }
}
