<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppMessageBatch extends Model
{
    protected $table = 'whatsapp_message_batches';

    protected $fillable = [
        'type',
        'source_type',
        'source_id',
        'title',
        'message_preview',
        'total_recipients',
        'sent_count',
        'failed_count',
        'mention_all',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'mention_all' => 'boolean',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsAppMessageLog::class, 'batch_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
