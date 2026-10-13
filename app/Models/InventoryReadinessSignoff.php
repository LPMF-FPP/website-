<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReadinessSignoff extends Model
{
    protected $fillable = ['evidence_id', 'signoff_role', 'actor_user_id', 'signed_at'];

    protected $casts = ['signed_at' => 'datetime'];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(InventoryReadinessEvidence::class, 'evidence_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
