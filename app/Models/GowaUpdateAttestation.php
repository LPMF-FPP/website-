<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GowaUpdateAttestation extends Model
{
    public $timestamps = false;

    protected $fillable = ['operation_id', 'fencing_token', 'plane', 'policy_version', 'snapshot_hash', 'container_identity', 'passed', 'observed_at'];

    protected $casts = ['passed' => 'boolean', 'observed_at' => 'datetime', 'fencing_token' => 'integer'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('GOWA attestations are immutable.');
        });
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(GowaUpdateOperation::class, 'operation_id');
    }
}
