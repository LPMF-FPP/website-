<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryItemLifecycleEvent extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'actor_user_id',
        'previous_is_active',
        'is_active',
        'event_type',
        'reason',
        'occurred_at',
    ];

    protected $casts = [
        'previous_is_active' => 'boolean',
        'is_active' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Item lifecycle events are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Item lifecycle events are append-only.'));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
