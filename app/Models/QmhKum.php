<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmhKum extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'year',
        'period',
        'scheduled_at',
        'location',
        'agenda',
        'minutes_content',
        'participants_json',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'scheduled_at' => 'datetime',
        'participants_json' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeSearch($query, ?string $keyword)
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where('title', 'like', '%'.$keyword.'%');
    }
}
