<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappBroadcast extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'message',
        'target_type',
        'target_filters',
        'recipient_ids',
        'created_by',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'error_log',
    ];

    protected $casts = [
        'target_filters' => 'array',
        'recipient_ids' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const TARGET_INVESTIGATORS = 'investigators';

    public const TARGET_USERS = 'users';

    public const TARGET_CUSTOM = 'custom';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public static function targetTypes(): array
    {
        return [
            self::TARGET_INVESTIGATORS => 'Penyidik',
            self::TARGET_USERS => 'Staff Internal',
            self::TARGET_CUSTOM => 'Kustom',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SCHEDULED => 'Terjadwal',
            self::STATUS_SENDING => 'Mengirim',
            self::STATUS_SENT => 'Terkirim',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappBroadcastRecipient::class, 'broadcast_id');
    }

    public function getTargetTypeLabelAttribute(): string
    {
        return self::targetTypes()[$this->target_type] ?? $this->target_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'green',
            self::STATUS_SENDING => 'blue',
            self::STATUS_SCHEDULED => 'yellow',
            self::STATUS_DRAFT => 'gray',
            self::STATUS_CANCELLED => 'red',
            default => 'gray',
        };
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return (int) round(($this->sent_count + $this->failed_count) / $this->total_recipients * 100);
    }

    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED]);
    }

    public function canSend(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->total_recipients > 0;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_SENDING]);
    }

    public function markAsSending(): void
    {
        $this->update([
            'status' => self::STATUS_SENDING,
            'started_at' => now(),
        ]);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'completed_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    public function incrementSentCount(): void
    {
        $this->increment('sent_count');
    }

    public function incrementFailedCount(): void
    {
        $this->increment('failed_count');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }

    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }
}
