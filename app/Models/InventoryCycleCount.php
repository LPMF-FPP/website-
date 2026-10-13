<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCycleCount extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'status',
        'is_partial',
        'purpose',
        'revision_number',
        'tuple_count',
        'counted_count',
        'created_by',
        'submitted_by',
        'submitted_at',
    ];

    protected $casts = [
        'is_partial' => 'boolean',
        'revision_number' => 'integer',
        'tuple_count' => 'integer',
        'counted_count' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCycleCountLine::class, 'cycle_count_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(InventoryCycleCountRevision::class, 'cycle_count_id')->orderBy('revision_number');
    }
}
