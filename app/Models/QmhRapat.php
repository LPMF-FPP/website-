<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmhRapat extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'meeting_type',
        'scheduled_at',
        'location',
        'agenda',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function pesertas(): HasMany
    {
        return $this->hasMany(QmhRapatPeserta::class, 'rapat_id');
    }

    public function notulensis(): HasMany
    {
        return $this->hasMany(QmhRapatNotulensi::class, 'rapat_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(QmhRapatActionItem::class, 'rapat_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QmhRapatAttachment::class, 'rapat_id')->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function latestNotulensi(): ?QmhRapatNotulensi
    {
        return $this->notulensis()->orderByDesc('version')->first();
    }

    public function scopeScheduled($query)
    {
        return $query->orderBy('scheduled_at');
    }

    public function scopeSearch($query, ?string $keyword)
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where(function ($subquery) use ($keyword) {
            $subquery
                ->where('title', 'like', '%'.$keyword.'%')
                ->orWhere('location', 'like', '%'.$keyword.'%');
        });
    }
}
