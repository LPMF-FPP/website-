<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLotTrackingPolicy extends Model
{
    public const LOT_TRACKED = 'lot_tracked';

    public const UNTRACKED = 'untracked';

    protected $primaryKey = 'item_type';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'lot_tracking_mode',
        'evidence_reference',
        'decided_by_user_id',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InventoryLotTrackingPolicyEvent::class, 'item_type', 'item_type');
    }
}
