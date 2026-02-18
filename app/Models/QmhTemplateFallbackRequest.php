<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmhTemplateFallbackRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'revision_id',
        'requested_clause',
        'requested_doc_type',
        'requested_layout_profile',
        'fallback_clause',
        'fallback_template_id',
        'status',
        'requested_by',
        'decided_by',
        'decision_note',
        'requested_at',
        'decided_at',
        'expires_at',
    ];

    protected $casts = [
        'requested_clause' => 'integer',
        'fallback_clause' => 'integer',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(QmhDocument::class, 'document_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(QmhDocumentRevision::class, 'revision_id');
    }

    public function fallbackTemplate(): BelongsTo
    {
        return $this->belongsTo(QmhTemplate::class, 'fallback_template_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
