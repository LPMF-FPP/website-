<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryIntegrityAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_id',
        'audited_at',
        'status',
        'tuple_count',
        'drift_count',
    ];

    protected $casts = [
        'audited_at' => 'immutable_datetime',
        'tuple_count' => 'integer',
        'drift_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \RuntimeException('Inventory integrity audits are immutable.');
        });

        static::deleting(static function (): never {
            throw new \RuntimeException('Inventory integrity audits cannot be deleted.');
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryIntegrityAuditLine::class, 'audit_id');
    }
}
