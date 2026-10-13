<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCycleCountDecision extends Model
{
    protected $fillable = [
        'revision_id',
        'actor_user_id',
        'decision',
        'reason',
        'resulting_source_fingerprint',
        'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(InventoryCycleCountRevision::class, 'revision_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
