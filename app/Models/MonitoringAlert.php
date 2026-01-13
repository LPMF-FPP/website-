<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringAlert extends Model
{
    protected $fillable = [
        'sensor_id',
        'type',
        'value',
        'threshold',
        'status',
        'notes',
        'resolved_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'threshold' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(MonitoringSensor::class, 'sensor_id');
    }
}
