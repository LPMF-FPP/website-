<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_type',
        'reference_type',
        'reference_id',
        'item_id',
        'lot_id',
        'from_location_id',
        'to_location_id',
        'qty',
        'uom',
        'unit_cost',
        'performed_by',
        'performed_at',
        'reason_code',
        'notes',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'performed_at' => 'datetime',
    ];

    public const MOVEMENT_TYPES = [
        'RECEIPT' => 'Penerimaan',
        'ISSUE' => 'Pengeluaran',
        'TRANSFER' => 'Transfer',
        'ADJUST' => 'Penyesuaian',
        'DISPOSE' => 'Disposal',
        'RETURN' => 'Retur',
    ];

    public const REFERENCE_TYPES = [
        'PURCHASE' => 'Pembelian',
        'TEST_JOB' => 'Pengujian',
        'STOCKTAKE' => 'Stock Opname',
        'MANUAL' => 'Manual',
        'DISPOSAL_DOC' => 'Dokumen Disposal',
        'CHANGELOG' => 'Changelogs',
    ];

    public const REASON_CODES = [
        'DAMAGE' => 'Rusak',
        'EXPIRED' => 'Kadaluarsa',
        'LOST' => 'Hilang',
        'CORRECTION' => 'Koreksi',
        'OTHER' => 'Lainnya',
    ];

    /**
     * Boot method to prevent updates/deletes on existing records.
     * This enforces the append-only (immutable ledger) constraint.
     */
    protected static function booted(): void
    {
        static::updating(function ($model) {
            throw new \RuntimeException('Inventory movements cannot be updated. Use an ADJUST movement instead.');
        });

        static::deleting(function ($model) {
            throw new \RuntimeException('Inventory movements cannot be deleted. Use an ADJUST movement instead.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'lot_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the movement type label.
     */
    public function getMovementTypeLabelAttribute(): string
    {
        return self::MOVEMENT_TYPES[$this->movement_type] ?? $this->movement_type;
    }

    /**
     * Get signed quantity (positive for IN, negative for OUT).
     */
    public function getSignedQtyAttribute(): float
    {
        $qty = (float) $this->qty;

        // Movements that increase stock at to_location
        $inTypes = ['RECEIPT', 'RETURN'];
        if (in_array($this->movement_type, $inTypes)) {
            return $qty;
        }

        // Movements that decrease stock at from_location
        $outTypes = ['ISSUE', 'DISPOSE'];
        if (in_array($this->movement_type, $outTypes)) {
            return -$qty;
        }

        // ADJUST can be positive or negative
        if ($this->movement_type === 'ADJUST') {
            // If has to_location, it's an increase; if from_location, decrease
            return $this->to_location_id ? $qty : -$qty;
        }

        // TRANSFER is neutral (one out, one in)
        return $qty;
    }

    /**
     * Scope for movements of a specific item.
     */
    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope for movements of a specific lot.
     */
    public function scopeForLot($query, int $lotId)
    {
        return $query->where('lot_id', $lotId);
    }

    /**
     * Scope for movements at a specific location.
     */
    public function scopeAtLocation($query, int $locationId)
    {
        return $query->where(function ($q) use ($locationId) {
            $q->where('from_location_id', $locationId)
                ->orWhere('to_location_id', $locationId);
        });
    }

    /**
     * Scope for movements within date range.
     */
    public function scopeBetweenDates($query, $from, $to)
    {
        if ($from) {
            $query->where('performed_at', '>=', $from);
        }
        if ($to) {
            $query->where('performed_at', '<=', $to);
        }

        return $query;
    }
}
