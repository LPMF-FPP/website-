<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLocationAccess extends Model
{
    protected $table = 'inventory_location_access';

    protected $fillable = ['user_id', 'location_id', 'can_view', 'can_transact'];

    protected $casts = [
        'can_view' => 'boolean',
        'can_transact' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }
}
