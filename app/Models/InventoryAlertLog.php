<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAlertLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_type',
        'item_id',
        'lot_id',
        'message',
        'recipients',
        'sent_to',
        'failed_to',
        'meta',
    ];

    protected $casts = [
        'recipients' => 'array',
        'sent_to' => 'array',
        'failed_to' => 'array',
        'meta' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'lot_id');
    }
}
