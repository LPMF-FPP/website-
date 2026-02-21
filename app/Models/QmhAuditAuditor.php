<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QmhAuditAuditor extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'audit_id',
        'user_id',
        'assigned_by',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(QmhAudit::class, 'audit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
