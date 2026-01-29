<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageLog extends Model
{
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
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageBatch::class, 'batch_id');
    }
}
