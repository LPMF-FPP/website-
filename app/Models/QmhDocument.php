<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'clause' => 'integer',
        'parent_sop_id' => 'integer',
        'paired_ik_id' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(QmhDocumentRevision::class, 'document_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(QmhDocumentRevision::class, 'current_revision_id');
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(QmhDocumentRevision::class, 'document_id')->latestOfMany('id');
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePendukung($query)
    {
        return $query->where('doc_type', 'pendukung');
    }

    public function isPendukung(): bool
    {
        return $this->doc_type === 'pendukung';
    }

    public function getLatestVersion(): int
    {
        $revision = $this->currentRevision ?? $this->latestRevision;

        if (! $revision instanceof QmhDocumentRevision) {
            return 0;
        }

        return max(1, ((int) $revision->revision_number) + 1);
    }

    public function fileUrl(): string
    {
        return route('quality.pendukung.file', ['document' => $this]);
    }

    public function getPendukungUsage(): Collection
    {
        return QmhDocumentRelation::query()
            ->where('target_document_id', $this->id)
            ->where('relation_type', 'pendukung')
            ->with('sourceDocument')
            ->get();
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
