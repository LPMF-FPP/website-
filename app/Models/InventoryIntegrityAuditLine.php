<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryIntegrityAuditLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audit_id',
        'item_id',
        'lot_id',
        'location_id',
        'balance_existed',
        'ledger_qty',
        'balance_qty',
        'delta_qty',
        'created_at',
    ];

    protected $casts = [
        'balance_existed' => 'boolean',
        'ledger_qty' => 'decimal:3',
        'balance_qty' => 'decimal:3',
        'delta_qty' => 'decimal:3',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \RuntimeException('Inventory integrity audit evidence is immutable.');
        });

        static::deleting(static function (): never {
            throw new \RuntimeException('Inventory integrity audit evidence cannot be deleted.');
        });
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InventoryIntegrityAudit::class, 'audit_id');
    }
}
