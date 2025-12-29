<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class EvidenceUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'sample_id',
        'receipt_code',
        'sample_code',
        'sample_type',
        'sample_desc',
        'investigator_name',
        'investigator_unit',
        'seal_status_received',
        'condition_received',
        'received_at',
        'received_by',
        'qr_token',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    /**
     * Boot method to auto-generate qr_token.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->qr_token)) {
                $model->qr_token = self::generateUniqueToken();
            }
        });
    }

    /**
     * Generate a unique 16-character token.
     */
    public static function generateUniqueToken(): string
    {
        do {
            $token = strtoupper(Str::random(16));
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }

    // ==================== RELATIONSHIPS ====================

    public function request(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class, 'request_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class, 'sample_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function remainingUnits(): HasMany
    {
        return $this->hasMany(RemainingUnit::class, 'evidence_unit_id');
    }

    public function printLogs(): MorphMany
    {
        return $this->morphMany(LabelPrintLog::class, 'printable');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get QR code content for the label.
     * Format: EVU:{id}:{token}
     */
    public function getQrContentAttribute(): string
    {
        return "EVU:{$this->id}:{$this->qr_token}";
    }

    /**
     * Get formatted receipt date.
     */
    public function getReceivedAtFormattedAttribute(): ?string
    {
        return $this->received_at?->format('d M Y H:i');
    }

    /**
     * Get truncated description for label.
     */
    public function getSampleDescTruncatedAttribute(): ?string
    {
        if (!$this->sample_desc) {
            return null;
        }
        return Str::limit($this->sample_desc, 80);
    }

    // ==================== SCOPES ====================

    public function scopeForRequest($query, int $requestId)
    {
        return $query->where('request_id', $requestId);
    }
}
