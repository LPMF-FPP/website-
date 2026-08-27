<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GowaUpdateOperation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public const ACTIVE_STATUSES = ['queued', 'preparing', 'updating', 'verifying', 'reconciling'];

    public const TERMINAL_STATUSES = ['succeeded', 'failed', 'rolled_back', 'degraded'];

    public const SCOPE = 'gowa';

    protected $fillable = [
        'id', 'scope', 'release_id', 'requested_version', 'requested_digest',
        'previous_version', 'previous_digest', 'status', 'idempotency_key',
        'fencing_token', 'checkpoint', 'root_authority_generation', 'heartbeat_at',
        'lease_expires_at', 'retry_of_id', 'requested_by', 'failure_code',
        'failure_message_key', 'preservation_snapshot', 'feature_snapshot', 'client_action_uuid',
    ];

    protected $casts = [
        'heartbeat_at' => 'datetime', 'lease_expires_at' => 'datetime',
        'preservation_snapshot' => 'array', 'feature_snapshot' => 'array',
        'fencing_token' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'retry_of_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GowaUpdateEvent::class, 'operation_id');
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(GowaUpdateAttestation::class, 'operation_id');
    }

    public function dispatchClaim(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GowaUpdateDispatchClaim::class, 'operation_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isStale(): bool
    {
        return ! $this->isTerminal() && $this->lease_expires_at?->isPast() === true;
    }

    /** @return array<string, mixed> */
    public function safeProjection(): array
    {
        return [
            'id' => (string) $this->id,
            'status' => $this->status,
            'release_id' => $this->release_id,
            'version' => $this->requested_version,
            'digest' => $this->requested_digest,
            'fencing_token' => $this->fencing_token,
            'checkpoint' => $this->checkpoint,
            'failure_code' => $this->failure_code,
            'message_key' => $this->failure_message_key,
            'retry_of_id' => $this->retry_of_id,
            'heartbeat_at' => $this->heartbeat_at?->toIso8601String(),
            'lease_expires_at' => $this->lease_expires_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'stale' => $this->lease_expires_at?->isPast() ?? false,
        ];
    }
}
