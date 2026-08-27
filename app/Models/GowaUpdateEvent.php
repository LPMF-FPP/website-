<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GowaUpdateEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['operation_id', 'runner_event_id', 'fencing_token', 'from_state', 'to_state', 'code', 'safe_meta', 'occurred_at'];

    protected $casts = ['safe_meta' => 'array', 'occurred_at' => 'datetime', 'fencing_token' => 'integer'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('GOWA update events are append-only.');
        });
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(GowaUpdateOperation::class, 'operation_id');
    }
}
