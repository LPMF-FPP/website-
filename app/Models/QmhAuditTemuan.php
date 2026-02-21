<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmhAuditTemuan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'audit_id',
        'title',
        'description',
        'severity',
        'corrective_action',
        'due_date',
        'status',
        'resolved_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(QmhAudit::class, 'audit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
