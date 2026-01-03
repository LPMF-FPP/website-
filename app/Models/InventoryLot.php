<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'lot_no',
        'expiry_date',
        'received_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_date' => 'date',
    ];

    public const STATUSES = [
        'ACTIVE' => 'Aktif',
        'QUARANTINE' => 'Karantina',
        'EXPIRED' => 'Kadaluarsa',
        'DISPOSED' => 'Dibuang',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'lot_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'lot_id');
    }

    /**
     * Check if lot is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    /**
     * Get days until expiry.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) Carbon::now()->startOfDay()->diffInDays($this->expiry_date, false);
    }

    /**
     * Check if lot is near expiry (within given days).
     */
    public function isNearExpiry(int $days = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }
        $daysUntil = $this->days_until_expiry;

        return $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= $days;
    }

    /**
     * Check if lot can be issued (not expired, not disposed).
     */
    public function canBeIssued(): bool
    {
        if ($this->status === 'DISPOSED') {
            return false;
        }
        if ($this->is_expired || $this->status === 'EXPIRED') {
            return false;
        }
        if ($this->status === 'QUARANTINE') {
            return false;
        }

        return true;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Scope for active lots.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope for expired lots.
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'EXPIRED')
                ->orWhere('expiry_date', '<', Carbon::today());
        });
    }

    /**
     * Scope for near-expiry lots.
     */
    public function scopeNearExpiry($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::today())
            ->where('expiry_date', '<=', Carbon::today()->addDays($days))
            ->where('status', '!=', 'DISPOSED');
    }

    /**
     * Scope ordered by FEFO (First Expiry First Out).
     */
    public function scopeFefo($query)
    {
        return $query->orderByRaw('expiry_date IS NULL, expiry_date ASC');
    }
}
