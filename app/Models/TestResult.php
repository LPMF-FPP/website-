<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'sample_id', 'tested_by', 'test_method', 'equipment_used',
        'test_conditions', 'active_substances', 'purity_percentage',
        'test_conclusion', 'result_status', 'chromatogram_path',
        'spectrum_path', 'analyst_notes', 'reviewed_by',
        'reviewed_at', 'qc_approved'
    ];

    protected $casts = [
        'active_substances' => 'array',
        'purity_percentage' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'qc_approved' => 'boolean',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
