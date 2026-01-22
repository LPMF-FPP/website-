<?php

namespace App\Models;

use App\Enums\EnvironmentLocationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnvironmentLocation extends Model
{
    protected $fillable = [
        'name',
        'type',
        'target_temp_min',
        'target_temp_max',
        'target_hum_min',
        'target_hum_max',
        'schedule_windows',
        'is_active',
        'pic_user_id',
    ];

    protected $casts = [
        'type' => EnvironmentLocationType::class,
        'target_temp_min' => 'decimal:2',
        'target_temp_max' => 'decimal:2',
        'target_hum_min' => 'decimal:2',
        'target_hum_max' => 'decimal:2',
        'schedule_windows' => 'array',
        'is_active' => 'boolean',
    ];

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(EnvironmentReading::class, 'location_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDefaultScheduleWindows(): array
    {
        return [
            ['start' => '07:00', 'end' => '09:00'],
            ['start' => '13:00', 'end' => '15:00'],
        ];
    }

    public function getScheduleWindowsAttribute($value): array
    {
        if ($value) {
            return is_string($value) ? json_decode($value, true) : $value;
        }

        return $this->getDefaultScheduleWindows();
    }
}
