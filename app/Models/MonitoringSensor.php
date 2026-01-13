<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringSensor extends Model
{
    protected $fillable = [
        'name',
        'code',
        'location',
        'type',
        'min_threshold',
        'max_threshold',
        'is_active',
        'last_reading_at',
        'last_value',
    ];

    protected $casts = [
        'min_threshold' => 'decimal:2',
        'max_threshold' => 'decimal:2',
        'last_value' => 'decimal:2',
        'is_active' => 'boolean',
        'last_reading_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class, 'sensor_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(MonitoringAlert::class, 'sensor_id');
    }
}
