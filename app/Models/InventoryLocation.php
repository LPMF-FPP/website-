<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location_type',
        'is_restricted',
    ];

    protected $casts = [
        'is_restricted' => 'boolean',
    ];

    public const LOCATION_TYPES = [
        'warehouse' => 'Gudang',
        'lab' => 'Laboratorium',
        'cold_storage' => 'Cold Storage',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'location_id');
    }

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'to_location_id');
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'from_location_id');
    }

    /**
     * Get the location type label.
     */
    public function getLocationTypeLabelAttribute(): string
    {
        return self::LOCATION_TYPES[$this->location_type] ?? $this->location_type;
    }
}
