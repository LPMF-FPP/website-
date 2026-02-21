<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmhAudit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'audit_type',
        'scope',
        'scheduled_at',
        'location',
        'status',
        'migration_phase',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /**
     * @return HasMany<QmhAuditAuditor>
     */
    public function auditAuditors(): HasMany
    {
        return $this->hasMany(QmhAuditAuditor::class, 'audit_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function auditors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'qmh_audit_auditors', 'audit_id', 'user_id')
            ->withPivot('assigned_by', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * @param  array<int, int|string>  $auditorIds
     */
    public function syncAuditors(array $auditorIds, ?int $assignedBy = null): void
    {
        $normalized = collect($auditorIds)
            ->map(fn (int|string $value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();

        $existing = $this->auditAuditors()->pluck('user_id')->map(fn ($id) => (int) $id);

        $toDelete = $existing->diff($normalized)->values();
        if ($toDelete->isNotEmpty()) {
            $this->auditAuditors()->whereIn('user_id', $toDelete->all())->delete();
        }

        foreach ($normalized as $userId) {
            QmhAuditAuditor::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'audit_id' => $this->id,
                        'user_id' => $userId,
                    ],
                    [
                        'assigned_by' => $assignedBy,
                        'deleted_at' => null,
                    ]
                );
        }
    }

    /**
     * @return array<int>
     */
    public function getAuditorIdsAttribute(): array
    {
        return $this->auditAuditors()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    public function temuans(): HasMany
    {
        return $this->hasMany(QmhAuditTemuan::class, 'audit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeSearch($query, ?string $keyword)
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where(function ($subquery) use ($keyword) {
            $subquery
                ->where('title', 'like', '%'.$keyword.'%')
                ->orWhere('scope', 'like', '%'.$keyword.'%');
        });
    }
}
