<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLotTrackingPolicyEvent extends Model
{
    protected $fillable = [
        'item_type',
        'actor_user_id',
        'previous_lot_tracking_mode',
        'lot_tracking_mode',
        'evidence_reference',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InventoryLotTrackingPolicy::class, 'item_type', 'item_type');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
