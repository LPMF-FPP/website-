<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpertWitnessRequest extends Model
{
    use HasFactory;

    public const SOURCE_FARMAPOL = 'farmapol';

    public const SOURCE_EXTERNAL = 'external';

    public const MILESTONES = [
        'draft_received' => 'Draft BAP diterima',
        'sprin_approved' => 'Sprin sahli telah disahkan',
        'draft_revised' => 'Revisi draft BAP selesai',
        'sent_to_investigator' => 'BAP dikirim ke penyidik',
        'signed_by_both' => 'BAP ditandatangani ahli dan penyidik',
    ];

    protected $fillable = [
        'source', 'test_request_id', 'investigator_id', 'submitted_by', 'submission_token', 'letter_number',
        'letter_date', 'investigator_name', 'investigator_rank', 'investigator_institution', 'investigator_phone', 'suspect_name',
        'sprin_number', 'sprin_date', 'notes', 'submitted_at', 'completed_at',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'sprin_date' => 'date',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ExpertWitnessMilestone::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ExpertWitnessDocument::class);
    }

    public function getReferenceDateAttribute(): ?\Carbon\Carbon
    {
        return $this->source === self::SOURCE_FARMAPOL
            ? $this->testRequest?->created_at
            : $this->submitted_at;
    }

    public function getNextMilestoneLabelAttribute(): string
    {
        foreach (self::MILESTONES as $code => $label) {
            if (! $this->milestones->firstWhere('code', $code)?->completed_at) {
                return $label;
            }
        }

        return 'Selesai';
    }
}
