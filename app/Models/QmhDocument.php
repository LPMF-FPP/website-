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
        'owner_label',
        'current_revision_id',
        'is_active',
    ];

    protected $casts = [
        'clause' => 'integer',
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
