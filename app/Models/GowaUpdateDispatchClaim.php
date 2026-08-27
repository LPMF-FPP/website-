<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GowaUpdateDispatchClaim extends Model
{
    protected $table = 'gowa_update_dispatch_claims';

    protected $fillable = [
        'operation_id',
        'scope',
        'release_id',
        'fencing_token',
        'claim_nonce',
        'owner',
        'catalog_generation',
        'revocation_generation',
        'claim_payload',
        'payload_hash',
        'claimed_at',
        'lease_expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'fencing_token' => 'integer',
        'claim_payload' => 'array',
        'claimed_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(GowaUpdateOperation::class, 'operation_id');
    }
}
