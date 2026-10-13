<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryOpeningBaseline extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_STALE = 'stale';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'status',
        'source_fingerprint',
        'policy_snapshot',
        'blockers',
        'tuple_count',
        'counted_count',
        'generated_by',
        'generated_at',
        'stale_at',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'submission_revision',
    ];

    protected $casts = [
        'policy_snapshot' => 'array',
        'blockers' => 'array',
        'tuple_count' => 'integer',
        'counted_count' => 'integer',
        'generated_at' => 'datetime',
        'stale_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'submission_revision' => 'integer',
    ];

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryOpeningBaselineLine::class, 'baseline_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(InventoryOpeningBaselineApproval::class, 'baseline_id')->orderBy('submission_revision');
    }
}
