<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryLocationAccessEvent extends Model
{
    protected $fillable = [
        'actor_user_id', 'target_user_id', 'location_id',
        'previous_can_view', 'previous_can_transact', 'can_view', 'can_transact',
        'action', 'reason', 'context', 'occurred_at',
    ];

    protected $casts = [
        'previous_can_view' => 'boolean',
        'previous_can_transact' => 'boolean',
        'can_view' => 'boolean',
        'can_transact' => 'boolean',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Location access events are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Location access events are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }
}
