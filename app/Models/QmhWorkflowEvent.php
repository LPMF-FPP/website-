<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmhWorkflowEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'revision_id',
        'event_type',
        'actor_id',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(QmhDocumentRevision::class, 'revision_id');
    }
}
