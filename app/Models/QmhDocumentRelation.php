<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmhDocumentRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_document_id',
        'target_document_id',
        'relation_type',
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(QmhDocument::class, 'source_document_id');
    }

    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(QmhDocument::class, 'target_document_id');
    }
}
