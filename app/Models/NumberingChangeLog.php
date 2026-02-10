<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NumberingChangeLog extends Model
{
    protected $fillable = [
        'scope',
        'action_type',
        'entity_type',
        'entity_id',
        'old_value',
        'new_value',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const ACTION_RESET = 'reset';

    public const ACTION_SYNC_MAX = 'sync_max';

    public const ACTION_SYNC_COUNT = 'sync_count';

    public const ACTION_EDIT = 'edit';

    public const ACTION_RECLAIM = 'reclaim';

    public const SCOPES = [
        'ba' => 'BA Penerimaan',
        'sample_code' => 'Kode Sampel',
        'lhu' => 'Laporan Hasil Uji',
        'ba_penyerahan' => 'BA Penyerahan',
        'tracking' => 'Nomor Resi',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function getScopeLabelAttribute(): string
    {
        return self::SCOPES[$this->scope] ?? $this->scope;
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action_type) {
            self::ACTION_RESET => 'Reset Manual',
            self::ACTION_SYNC_MAX => 'Sinkronisasi (Tertinggi)',
            self::ACTION_SYNC_COUNT => 'Sinkronisasi (Jumlah)',
            self::ACTION_EDIT => 'Edit Nomor',
            self::ACTION_RECLAIM => 'Reclaim Gap',
            default => $this->action_type,
        };
    }

    public static function log(
        string $scope,
        string $actionType,
        string $oldValue,
        string $newValue,
        string $reason,
        ?string $entityType = null,
        ?int $entityId = null
    ): self {
        return self::create([
            'scope' => $scope,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'user_id' => auth()->id() ?? \App\Models\User::first()?->id ?? \App\Models\User::factory()->create()->id,
        ]);
    }
}
