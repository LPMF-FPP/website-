<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappOutbox extends Model
{
    protected $table = 'whatsapp_outbox';

    protected $fillable = [
        'test_request_id',
        'milestone_key',
        'to_phone_e164',
        'to_jid',
        'message_text',
        'provider_message_id',
        'status',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
    ];

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }
}
