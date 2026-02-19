<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappCommandLog extends Model
{
    protected $fillable = [
        'from_jid',
        'from_phone_e164',
        'message_text',
        'provider_message_id',
        'message_fingerprint',
        'command',
        'params',
        'response_status',
        'response_text',
        'processed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'processed_at' => 'datetime',
    ];
}
