<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GowaUpdatePreparation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'requested_by', 'action_uuid', 'idempotency_key', 'status',
        'requested_version', 'release_id', 'digest', 'catalog_generation',
        'runtime_digest', 'container_identity', 'failure_code', 'requested_at',
        'prepared_at', 'expires_at', 'consumed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'prepared_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return array<string, mixed> */
    public function safeProjection(): array
    {
        $ready = $this->status === 'ready'
            && $this->consumed_at === null
            && $this->expires_at?->isFuture() === true;

        return [
            'id' => (string) $this->id,
            'status' => $this->status,
            'version' => $this->requested_version,
            'release_id' => $this->release_id,
            'digest' => $this->digest,
            'failure_code' => $this->failure_code,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'prepared_at' => $this->prepared_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'ready' => $ready,
        ];
    }
}
