<?php

namespace App\Models;

use App\Enums\InstrumentAssetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentAsset extends Model
{
    protected $fillable = [
        'instrument_id',
        'asset_code',
        'serial_number',
        'location',
        'status',
        'last_calibration_at',
        'calibration_due_at',
        'notes',
    ];

    protected $casts = [
        'status' => InstrumentAssetStatus::class,
        'last_calibration_at' => 'date',
        'calibration_due_at' => 'date',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(InstrumentUsageLog::class);
    }

    public function isAvailable(): bool
    {
        return $this->status->isAvailable();
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', InstrumentAssetStatus::ACTIVE);
    }

    public function scopeForInstrument($query, int $instrumentId)
    {
        return $query->where('instrument_id', $instrumentId);
    }

    public function scopeCalibrationDue($query)
    {
        return $query->where('calibration_due_at', '<=', now()->toDateString());
    }
}
