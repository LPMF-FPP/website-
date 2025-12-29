<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_type',
        'name',
        'brand',
        'manufacturer',
        'specification',
        'uom',
        'pack_size',
        'is_hazardous',
        'hazard_class',
        'storage_condition',
        'min_stock',
        'is_active',
    ];

    protected $casts = [
        'pack_size' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'is_hazardous' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const ITEM_TYPES = [
        'REAGENT' => 'Reagen',
        'CONSUMABLE' => 'Consumable/BHP',
        'STANDARD' => 'Standar',
        'CONTROL' => 'Kontrol',
        'OTHER' => 'Lainnya',
    ];

    public const STORAGE_CONDITIONS = [
        'RT' => 'Room Temperature (15-25°C)',
        '2-8C' => 'Refrigerated (2-8°C)',
        '-20C' => 'Frozen (-20°C)',
        '-80C' => 'Ultra-low (-80°C)',
    ];

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'item_id');
    }

    /**
     * Check if this item type requires expiry tracking.
     */
    public function requiresExpiry(): bool
    {
        return in_array($this->item_type, ['REAGENT', 'STANDARD', 'CONTROL']);
    }

    /**
     * Get total on-hand quantity across all locations.
     */
    public function getTotalOnHandAttribute(): float
    {
        return (float) $this->balances()->sum('on_hand_qty');
    }

    /**
     * Check if item is below minimum stock level.
     */
    public function getIsBelowMinStockAttribute(): bool
    {
        return $this->total_on_hand < $this->min_stock;
    }

    /**
     * Get the item type label.
     */
    public function getItemTypeLabelAttribute(): string
    {
        return self::ITEM_TYPES[$this->item_type] ?? $this->item_type;
    }

    /**
     * Scope for active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for items by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Scope for items below minimum stock.
     */
    public function scopeBelowMinStock($query)
    {
        return $query->whereRaw('(SELECT COALESCE(SUM(on_hand_qty), 0) FROM inventory_balances WHERE item_id = inventory_items.id) < min_stock');
    }
}
