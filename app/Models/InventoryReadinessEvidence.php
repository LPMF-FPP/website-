<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReadinessEvidence extends Model
{
    public const CATEGORIES = [
        'permission_grants' => 'Penetapan izin dan akses lokasi',
        'lot_item_policies' => 'Keputusan kebijakan item dan lot',
        'rehearsal' => 'Rehearsal operasional non-produksi',
        'postgresql_guards' => 'Uji PostgreSQL, pdo_pgsql, dan database guard',
        'backup_restore' => 'Uji backup dan pemulihan',
        'monitoring_escalation' => 'Monitoring dan jalur eskalasi',
        'disable_rollback' => 'Prosedur disable dan rollback',
    ];

    protected $fillable = ['category', 'version', 'evidence_reference', 'state_fingerprint', 'recorded_by', 'recorded_at'];

    protected $casts = ['recorded_at' => 'datetime'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function signoffs(): HasMany
    {
        return $this->hasMany(InventoryReadinessSignoff::class, 'evidence_id');
    }
}
