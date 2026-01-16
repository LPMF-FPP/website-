<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'permission_id',
        'granted',
    ];

    protected $casts = [
        'granted' => 'boolean',
    ];

    /**
     * Get the user for this user permission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the permission for this user permission.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter granted permissions.
     */
    public function scopeGranted($query)
    {
        return $query->where('granted', true);
    }

    /**
     * Scope to filter revoked permissions.
     */
    public function scopeRevoked($query)
    {
        return $query->where('granted', false);
    }

    /**
     * Get all custom permissions for a specific user.
     */
    public static function getCustomPermissionsForUser(int $userId): array
    {
        return static::byUser($userId)
            ->with('permission')
            ->get()
            ->mapWithKeys(function ($userPermission) {
                return [$userPermission->permission->name => $userPermission->granted];
            })
            ->toArray();
    }
}
