<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'lot_id',
        'location_id',
        'on_hand_qty',
        'reserved_qty',
        'updated_at',
    ];

    protected $casts = [
        'on_hand_qty' => 'decimal:3',
        'reserved_qty' => 'decimal:3',
        'updated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'lot_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    /**
     * Get available quantity (on_hand - reserved).
     */
    public function getAvailableQtyAttribute(): float
    {
        return (float) $this->on_hand_qty - (float) $this->reserved_qty;
    }

    /**
     * Find or create balance record for item/lot/location combination.
     */
    public static function findOrCreateFor(int $itemId, ?int $lotId, int $locationId): self
    {
        return self::firstOrCreate([
            'item_id' => $itemId,
            'lot_id' => $lotId,
            'location_id' => $locationId,
        ], [
            'on_hand_qty' => 0,
            'reserved_qty' => 0,
            'updated_at' => now(),
        ]);
    }

    /**
     * Increase on-hand quantity.
     */
    public function increaseOnHand(float $qty): self
    {
        $this->on_hand_qty = (float) $this->on_hand_qty + $qty;
        $this->updated_at = now();
        $this->save();

        return $this;
    }

    /**
     * Decrease on-hand quantity.
     *
     * @throws \RuntimeException if would result in negative balance
     */
    public function decreaseOnHand(float $qty, bool $allowNegative = false): self
    {
        $newQty = (float) $this->on_hand_qty - $qty;

        if (! $allowNegative && $newQty < 0) {
            throw new \RuntimeException(
                "Insufficient stock. Available: {$this->on_hand_qty}, Requested: {$qty}"
            );
        }

        $this->on_hand_qty = $newQty;
        $this->updated_at = now();
        $this->save();

        return $this;
    }

    /**
     * Scope for balances of a specific item.
     */
    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope for balances at a specific location.
     */
    public function scopeAtLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Scope for balances with positive on-hand.
     */
    public function scopeWithStock($query)
    {
        return $query->where('on_hand_qty', '>', 0);
    }
}
