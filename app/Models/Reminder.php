<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reminder extends Model
{
    protected $fillable = [
        'type',
        'name',
        'description',
        'is_enabled',
        'schedule_time',
        'schedule_days',
        'message_template',
        'metadata',
        'last_run_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'metadata' => 'array',
        'last_run_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(ReminderRecipient::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeDue(Builder $query): Builder
    {
        // Simple check for time match.
        // More complex logic (day of week) will be handled in the service/command
        // to keep the query simple and database-agnostic regarding time functions
        return $query->where('is_enabled', true)
            ->where('schedule_time', '<=', now()->format('H:i:s'))
            ->where(function ($q) {
                $q->whereNull('last_run_at')
                    ->orWhereDate('last_run_at', '<', now()->toDateString());
            });
    }
}
