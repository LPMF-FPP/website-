<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageAttempt extends Model
{
    protected $table = 'whatsapp_message_attempts';

    protected $fillable = [
        'whatsapp_message_log_id',
        'attempt_number',
        'status',
        'provider_status',
        'provider_message_id',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'provider_status' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageLog::class, 'whatsapp_message_log_id');
    }
}
