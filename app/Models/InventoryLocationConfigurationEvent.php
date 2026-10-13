<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLocationConfigurationEvent extends Model
{
    public const EVENT_TYPES = ['created', 'updated'];

    protected $fillable = [
        'location_id',
        'actor_user_id',
        'event_type',
        'previous_configuration',
        'new_configuration',
        'occurred_at',
    ];

    protected $casts = [
        'previous_configuration' => 'array',
        'new_configuration' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
