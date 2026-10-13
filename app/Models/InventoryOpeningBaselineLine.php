<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOpeningBaselineLine extends Model
{
    protected $fillable = [
        'baseline_id',
        'item_id',
        'lot_id',
        'location_id',
        'item_code_snapshot',
        'item_name_snapshot',
        'item_type_snapshot',
        'uom_snapshot',
        'lot_no_snapshot',
        'location_name_snapshot',
        'location_type_snapshot',
        'system_on_hand_qty',
        'ledger_on_hand_qty',
        'reserved_qty_snapshot',
        'balance_existed',
        'physical_qty',
        'counted_by',
        'counted_at',
    ];

    protected $casts = [
        'system_on_hand_qty' => 'decimal:3',
        'ledger_on_hand_qty' => 'decimal:3',
        'reserved_qty_snapshot' => 'decimal:3',
        'physical_qty' => 'decimal:3',
        'balance_existed' => 'boolean',
        'counted_at' => 'datetime',
    ];

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(InventoryOpeningBaseline::class, 'baseline_id');
    }

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

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
