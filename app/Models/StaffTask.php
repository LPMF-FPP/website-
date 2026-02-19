<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'assigned_to',
        'assigned_by',
        'test_request_id',
        'source_module',
        'source_ref_type',
        'source_ref_id',
        'workflow_stage',
        'action_token_hash',
        'action_expires_at',
        'token_consumed_at',
        'context_json',
        'priority',
        'status',
        'due_at',
        'started_at',
        'completed_at',
        'notes',
        'notify_whatsapp',
        'notification_sent',
        'notification_sent_at',
    ];

    protected $casts = [
        'source_ref_id' => 'integer',
        'context_json' => 'array',
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'action_expires_at' => 'datetime',
        'token_consumed_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'notify_whatsapp' => 'boolean',
        'notification_sent' => 'boolean',
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_MODULE_QMH = 'qmh';

    public const SOURCE_REF_TYPE_QMH_REVISION = 'qmh_document_revision';

    public const WORKFLOW_STAGE_REVIEW = 'review';

    public const WORKFLOW_STAGE_APPROVAL = 'approval';

    public static function priorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Rendah',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'Tinggi',
            self::PRIORITY_URGENT => 'Mendesak',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_IN_PROGRESS => 'Dikerjakan',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::priorities()[$this->priority] ?? $this->priority;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'red',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_NORMAL => 'blue',
            self::PRIORITY_LOW => 'gray',
            default => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_IN_PROGRESS => 'blue',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    public function isOverdue(): bool
    {
        if (! $this->due_at) {
            return false;
        }

        return $this->due_at->isPast() && ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeForRequest($query, int $requestId)
    {
        return $query->where('test_request_id', $requestId);
    }

    public function scopeQmhWorkflow($query)
    {
        return $query->where('source_module', self::SOURCE_MODULE_QMH)
            ->where('source_ref_type', self::SOURCE_REF_TYPE_QMH_REVISION);
    }

    public function scopeForQmhRevision($query, int $revisionId)
    {
        return $query->qmhWorkflow()->where('source_ref_id', $revisionId);
    }

    public function hasActiveActionToken(): bool
    {
        if ($this->action_token_hash === null || $this->action_token_hash === '') {
            return false;
        }

        if ($this->token_consumed_at !== null) {
            return false;
        }

        if ($this->action_expires_at !== null && $this->action_expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
