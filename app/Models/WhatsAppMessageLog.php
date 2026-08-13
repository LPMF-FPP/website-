<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppMessageLog extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_BLOCKED = 'blocked';

    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'batch_id',
        'recipient_jid',
        'recipient_name',
        'recipient_type',
        'status',
        'error_message',
        'message_id',
        'sent_at',
        'transport',
        'payload_encrypted',
        'attachment_disk',
        'attachment_path',
        'attachment_filename',
        'attachment_mime',
        'attachment_size',
        'source_type',
        'source_id',
        'source_label',
        'idempotency_key',
        'retryable',
        'retry_block_reason',
        'attempt_count',
        'claimed_at',
        'completed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'retryable' => 'boolean',
        'attempt_count' => 'integer',
        'attachment_size' => 'integer',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'payload_encrypted',
        'attachment_disk',
        'attachment_path',
        'attachment_filename',
        'attachment_mime',
        'attachment_size',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageBatch::class, 'batch_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WhatsAppMessageAttempt::class, 'whatsapp_message_log_id');
    }

    public function canRetry(): bool
    {
        return $this->transport === 'gowa'
            && $this->status === self::STATUS_FAILED
            && $this->retryable
            && is_string($this->payload_encrypted)
            && $this->payload_encrypted !== '';
    }
}
