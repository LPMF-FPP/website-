<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmhDocumentDownloadLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'revision_id',
        'edition_number',
        'revision_number',
        'copy_type',
        'downloaded_by',
        'downloaded_at',
        'reason',
        'distribution_target',
        'watermark_text',
        'file_hash',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'edition_number' => 'integer',
        'revision_number' => 'integer',
        'downloaded_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(QmhDocument::class, 'document_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(QmhDocumentRevision::class, 'revision_id');
    }
}
