<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryReopening extends Model
{
    protected $fillable = [
        'test_request_id',
        'delivery_id',
        'sample_id',
        'reopened_by',
        'handover_cycle',
        'previous_request_status',
        'previous_delivery_status',
        'previous_delivery_date',
        'previous_ready_for_delivery_at',
        'previous_completed_at',
        'reason',
        'superseded_document_ids',
        'superseded_message_log_ids',
        'reopened_at',
    ];

    protected $casts = [
        'handover_cycle' => 'integer',
        'previous_ready_for_delivery_at' => 'datetime',
        'previous_completed_at' => 'datetime',
        'previous_delivery_date' => 'datetime',
        'superseded_document_ids' => 'array',
        'superseded_message_log_ids' => 'array',
        'reopened_at' => 'datetime',
    ];

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
