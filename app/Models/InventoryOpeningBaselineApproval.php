<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryOpeningBaselineApproval extends Model
{
    protected $fillable = [
        'baseline_id',
        'submission_revision',
        'submitted_by',
        'approved_by',
        'decision',
        'decision_note',
        'source_fingerprint',
        'resulting_source_fingerprint',
        'approved_lines_snapshot',
        'decided_at',
    ];

    protected $casts = [
        'approved_lines_snapshot' => 'array',
        'decided_at' => 'datetime',
    ];

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(InventoryOpeningBaseline::class, 'baseline_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
