<?php

namespace App\Models;

use App\Enums\ReadingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentReading extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'measured_at',
        'temperature_c',
        'humidity_rh',
        'entered_by',
        'source',
        'notes',
        'correction_of_id',
        'correction_reason',
    ];

    protected $casts = [
        'measured_at' => 'datetime',
        'temperature_c' => 'decimal:2',
        'humidity_rh' => 'decimal:2',
        'source' => ReadingSource::class,
        'created_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(EnvironmentLocation::class, 'location_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function originalReading(): BelongsTo
    {
        return $this->belongsTo(EnvironmentReading::class, 'correction_of_id');
    }

    public function isCorrection(): bool
    {
        return $this->correction_of_id !== null;
    }

    public function isOutOfRange(): bool
    {
        $location = $this->location;
        if (! $location) {
            return false;
        }

        $tempOutOfRange = false;
        if ($this->temperature_c !== null) {
            if ($location->target_temp_min !== null && $this->temperature_c < $location->target_temp_min) {
                $tempOutOfRange = true;
            }
            if ($location->target_temp_max !== null && $this->temperature_c > $location->target_temp_max) {
                $tempOutOfRange = true;
            }
        }

        $humOutOfRange = false;
        if ($this->humidity_rh !== null) {
            if ($location->target_hum_min !== null && $this->humidity_rh < $location->target_hum_min) {
                $humOutOfRange = true;
            }
            if ($location->target_hum_max !== null && $this->humidity_rh > $location->target_hum_max) {
                $humOutOfRange = true;
            }
        }

        return $tempOutOfRange || $humOutOfRange;
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('measured_at', $date);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('measured_at', $year)
            ->whereMonth('measured_at', $month);
    }

    public function scopeOriginalOnly($query)
    {
        return $query->whereNull('correction_of_id');
    }
}
