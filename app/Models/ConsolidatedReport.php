<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsolidatedReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'period_type',
        'period_start',
        'period_end',
        'period_label',
        'report_data',
        'comparison_data',
        'narrative_sections',
        'signers',
        'generated_by',
        'generated_at',
        'is_auto_generated',
        'pdf_path',
        'pdf_size',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'report_data' => 'array',
        'comparison_data' => 'array',
        'narrative_sections' => 'array',
        'signers' => 'array',
        'generated_at' => 'datetime',
        'is_auto_generated' => 'boolean',
    ];

    /**
     * Accessors to append to model's array/JSON form.
     */
    protected $appends = ['download_url'];

    /**
     * Get the user who generated the report.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the download URL for the report PDF.
     * Always returns the URL - controller handles regeneration if PDF is missing.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('consolidated-reports.download', $this);
    }
}
