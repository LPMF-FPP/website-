<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QmhDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'doc_code',
        'title',
        'clause',
        'doc_type',
        'parent_sop_id',
        'paired_ik_id',
        'owner_label',
        'current_revision_id',
        'is_active',
    ];

    protected $casts = [
        'clause' => 'integer',
        'parent_sop_id' => 'integer',
        'paired_ik_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(QmhDocumentRevision::class, 'document_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(QmhDocumentRevision::class, 'current_revision_id');
    }

    public function parentSop(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_sop_id');
    }

    public function pairedIk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paired_ik_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_sop_id');
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(QmhDocumentDownloadLog::class, 'document_id');
    }

    public function relatedDocuments(): HasMany
    {
        return $this->hasMany(QmhDocumentRelation::class, 'source_document_id');
    }

    public function referencedByDocuments(): HasMany
    {
        return $this->hasMany(QmhDocumentRelation::class, 'target_document_id');
    }

    public function scopeSearch($query, ?string $keyword)
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where(function ($subquery) use ($keyword) {
            $subquery->where('doc_code', 'like', '%'.$keyword.'%')
                ->orWhere('title', 'like', '%'.$keyword.'%');
        });
    }
}
