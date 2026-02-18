<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QmhDocumentRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'edition_number',
        'revision_number',
        'version_label',
        'status',
        'template_id',
        'template_name',
        'template_version',
        'source_pdf_disk',
        'source_pdf_path',
        'source_pdf_sha256',
        'source_pdf_mime',
        'source_pdf_size',
        'source_pdf_page_count',
        'source_pdf_uploaded_at',
        'layout_checker_status',
        'layout_checker_payload',
        'layout_checker_checked_at',
        'attestation_actor',
        'attestation_reason',
        'attestation_incident_ref',
        'attestation_recorded_at',
        'last_autosaved_at',
        'content_version',
        'change_summary',
        'version_bump_mode',
        'editor_json',
        'answers_json',
        'form_schema_json',
        'content_html',
        'content_css',
        'dibuat_oleh',
        'diperiksa_oleh',
        'disahkan_oleh',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'effective_date',
        'obsolete_at',
    ];

    protected $casts = [
        'edition_number' => 'integer',
        'revision_number' => 'integer',
        'template_version' => 'integer',
        'content_version' => 'integer',
        'source_pdf_size' => 'integer',
        'source_pdf_page_count' => 'integer',
        'editor_json' => 'array',
        'answers_json' => 'array',
        'form_schema_json' => 'array',
        'layout_checker_payload' => 'array',
        'source_pdf_uploaded_at' => 'datetime',
        'layout_checker_checked_at' => 'datetime',
        'attestation_recorded_at' => 'datetime',
        'last_autosaved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'effective_date' => 'date',
        'obsolete_at' => 'datetime',
    ];

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'in_review' && $this->submitted_at) {
            return $this->submitted_at->diffInDays(now()) > 7;
        }

        if ($this->status === 'draft') {
            return $this->created_at->diffInDays(now()) > 30;
        }

        return false;
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(QmhDocument::class, 'document_id');
    }

    public function workflowEvents(): HasMany
    {
        return $this->hasMany(QmhWorkflowEvent::class, 'revision_id');
    }

    public function lock(): HasOne
    {
        return $this->hasOne(QmhDocumentLock::class, 'revision_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QmhTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperiksa_oleh');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(QmhDocumentDownloadLog::class, 'revision_id');
    }
}
