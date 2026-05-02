<?php

namespace App\Models;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'title_prefix',
        'title_suffix',
        'rank',
        'nrp',
        'nip',
        'investigator_id',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function getDisplayNameWithTitleAttribute(): string
    {
        $parts = array_filter([
            $this->title_prefix ? trim($this->title_prefix) : null,
            $this->name ? trim($this->name) : null,
            $this->title_suffix ? trim($this->title_suffix) : null,
        ]);

        if (count($parts) === 0) {
            return (string) $this->name;
        }

        return implode(' ', $parts);
    }

    public function getIdentificationNumberAttribute(): ?string
    {
        return $this->nrp ?: $this->nip;
    }

    // ============================================
    // Permission Relationships & Methods
    // ============================================

    /**
     * Get custom permissions untuk user ini.
     */
    public function customPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * Get permissions melalui pivot table.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot('granted')
            ->withTimestamps();
    }

    public function googleDriveToken(): HasOne
    {
        return $this->hasOne(UserGoogleDriveToken::class);
    }

    /**
     * Cek apakah user memiliki permission tertentu.
     *
     * @param  string  $permissionName  e.g. "permintaan.view"
     */
    public function hasPermission(string $permissionName): bool
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->userCan($this, $permissionName);
    }

    /**
     * Cek apakah user memiliki salah satu dari permissions.
     */
    public function hasAnyPermission(array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah user memiliki semua permissions.
     */
    public function hasAllPermissions(array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Grant permission kepada user (custom override).
     */
    public function grantPermission(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();

        if (! $permission) {
            return;
        }

        UserPermission::updateOrCreate(
            [
                'user_id' => $this->id,
                'permission_id' => $permission->id,
            ],
            [
                'granted' => true,
            ]
        );

        $this->clearPermissionCache();
    }

    /**
     * Revoke permission dari user (custom override).
     */
    public function revokePermission(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();

        if (! $permission) {
            return;
        }

        UserPermission::updateOrCreate(
            [
                'user_id' => $this->id,
                'permission_id' => $permission->id,
            ],
            [
                'granted' => false,
            ]
        );

        $this->clearPermissionCache();
    }

    /**
     * Reset semua permission ke default role.
     */
    public function resetPermissionsToRole(): void
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);
        $service->resetToRoleDefaults($this);
    }

    /**
     * Clear permission cache untuk user ini.
     */
    public function clearPermissionCache(): void
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);
        $service->clearUserCache($this);
    }

    /**
     * Get semua permissions untuk user (combined role + custom).
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->getUserPermissions($this);
    }

    /**
     * Get permissions grouped by module untuk UI.
     */
    public function getPermissionsForUI(): array
    {
        /** @var PermissionService $service */
        $service = app(PermissionService::class);

        return $service->getPermissionsForUI($this);
    }
}
