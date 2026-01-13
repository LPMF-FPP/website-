<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringLog extends Model
{
    protected $fillable = [
        'sensor_id',
        'value',
        'secondary_value',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'secondary_value' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(MonitoringSensor::class, 'sensor_id');
    }
}
