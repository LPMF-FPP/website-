<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappCommandLog extends Model
{
    protected $fillable = [
        'from_jid',
        'from_phone_e164',
        'message_text',
        'command',
        'params',
        'response_status',
        'response_text',
    ];

    protected $casts = [
        'params' => 'array',
    ];
}
