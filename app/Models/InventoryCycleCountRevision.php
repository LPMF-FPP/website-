<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryCycleCountRevision extends Model
{
    protected $fillable = [
        'cycle_count_id',
        'revision_number',
        'revision_status',
        'submitted_by',
        'snapshot_hash',
        'lines_snapshot',
        'submitted_at',
    ];

    protected $casts = [
        'revision_number' => 'integer',
        'lines_snapshot' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function cycleCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCycleCount::class, 'cycle_count_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decision(): HasOne
    {
        return $this->hasOne(InventoryCycleCountDecision::class, 'revision_id');
    }
}
