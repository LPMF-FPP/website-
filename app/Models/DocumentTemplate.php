<?php

namespace App\Models;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'format',
        'name',
        'storage_path',
        'content_html',
        'content_css',
        'is_active',
        'version',
        'checksum',
        'meta',
        'created_by',
        'updated_by',
        'render_engine',
        'doc_type',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'type' => DocumentType::class,
        'format' => DocumentFormat::class,
        'is_active' => 'boolean',
        'version' => 'integer',
        'meta' => 'array',
        'render_engine' => DocumentRenderEngine::class,
        'issued_at' => 'datetime',
    ];

    /**
     * Get the user who created this template
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this template
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get only active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get templates by type
     */
    public function scopeOfType($query, DocumentType $type)
    {
        return $query->where('type', $type->value);
    }

    /**
     * Scope to get templates by format
     */
    public function scopeOfFormat($query, DocumentFormat $format)
    {
        return $query->where('format', $format->value);
    }

    /**
     * Get the full file path for this template
     */
    public function getFullPathAttribute(): string
    {
        $disk = data_get($this->meta, 'disk', config('filesystems.default'));
        return storage_path('app/' . $this->storage_path);
    }

    /**
     * Scope to get templates by doc_type (BA|LHU).
     */
    public function scopeOfDocType($query, string $docType)
    {
        return $query->where('doc_type', $docType);
    }

    /**
     * Scope to get templates with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get issued templates.
     */
    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    /**
     * Scope to get draft templates.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Check if template is issued.
     */
    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    /**
     * Check if template is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if template is obsolete.
     */
    public function isObsolete(): bool
    {
        return $this->status === 'obsolete';
    }

    /**
     * Mark template as issued.
     */
    public function markAsIssued(): bool
    {
        $this->status = 'issued';
        $this->issued_at = now();
        return $this->save();
    }

    /**
     * Mark template as obsolete.
     */
    public function markAsObsolete(): bool
    {
        $this->status = 'obsolete';
        $this->is_active = false;
        return $this->save();
    }
}
