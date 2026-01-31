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
        'mention_all',
        'last_run_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'metadata' => 'array',
        'mention_all' => 'boolean',
        'last_run_at' => 'datetime',
        'schedule_days' => 'array', // Auto convert JSON <-> PHP Array
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
        // Check if today is in the schedule_days array
        $today = now()->format('D'); // Mon, Tue, etc.

        return $query->where('is_enabled', true)
            ->where('schedule_time', '<=', now()->format('H:i:s'))
            // Filter by day: Check if JSON array contains today's short name
            ->whereJsonContains('schedule_days', $today)
            ->where(function ($q) {
                $q->whereNull('last_run_at')
                    ->orWhereDate('last_run_at', '<', now()->toDateString());
            });
    }
}
