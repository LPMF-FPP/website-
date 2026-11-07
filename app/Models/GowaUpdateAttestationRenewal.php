<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GowaUpdateAttestationRenewal extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = ['operation_id', 'fencing_token', 'plane', 'policy_version', 'snapshot_hash', 'container_identity', 'passed', 'observed_at'];

    protected $casts = ['passed' => 'boolean', 'observed_at' => 'datetime', 'fencing_token' => 'integer'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('GOWA attestation renewals are immutable.'));
        self::deleting(fn () => throw new \LogicException('GOWA attestation renewals are immutable.'));
    }
}
