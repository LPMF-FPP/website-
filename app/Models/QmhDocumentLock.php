<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmhDocumentLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'revision_id',
        'locked_by',
        'locked_at',
        'heartbeat_at',
        'expires_at',
        'force_unlocked_by',
        'force_unlocked_reason',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(QmhDocumentRevision::class, 'revision_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isActive(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
