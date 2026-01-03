<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class RemainingUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'evidence_unit_id',
        'sample_code',
        'remaining_code',
        'qty_remaining',
        'uom',
        'seal_status_delivered',
        'condition_delivered',
        'delivered_at',
        'delivered_by',
        'handover_doc_no',
        'qr_token',
    ];

    protected $casts = [
        'qty_remaining' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    /**
     * Boot method to auto-generate qr_token and remaining_code.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            // Auto-generate qr_token
            if (empty($model->qr_token)) {
                $model->qr_token = self::generateUniqueToken();
            }

            // Auto-generate remaining_code if not set
            if (empty($model->remaining_code) && $model->evidence_unit_id) {
                $model->remaining_code = self::generateRemainingCode($model->evidence_unit_id);
            }

            // Denormalize sample_code from evidence unit
            if (empty($model->sample_code) && $model->evidence_unit_id) {
                $evidenceUnit = EvidenceUnit::find($model->evidence_unit_id);
                if ($evidenceUnit) {
                    $model->sample_code = $evidenceUnit->sample_code;
                }
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

    /**
     * Generate remaining_code based on sample_code.
     * Format: {sample_code}-SISA or {sample_code}-SISA-n
     */
    public static function generateRemainingCode(int $evidenceUnitId): string
    {
        $evidenceUnit = EvidenceUnit::findOrFail($evidenceUnitId);
        $sampleCode = $evidenceUnit->sample_code;

        // Count existing remaining units for this evidence unit
        $existingCount = self::where('evidence_unit_id', $evidenceUnitId)->count();

        if ($existingCount === 0) {
            // First remaining unit: {sample_code}-SISA
            return "{$sampleCode}-SISA";
        }

        // Subsequent remaining units: {sample_code}-SISA-{n}
        // Find the next available number
        $nextNumber = $existingCount + 1;
        $code = "{$sampleCode}-SISA-{$nextNumber}";

        // Ensure uniqueness (in case of manual entries)
        while (self::where('remaining_code', $code)->exists()) {
            $nextNumber++;
            $code = "{$sampleCode}-SISA-{$nextNumber}";
        }

        return $code;
    }

    // ==================== RELATIONSHIPS ====================

    public function evidenceUnit(): BelongsTo
    {
        return $this->belongsTo(EvidenceUnit::class, 'evidence_unit_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function printLogs(): MorphMany
    {
        return $this->morphMany(LabelPrintLog::class, 'printable');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get QR code content for the label.
     * Format: REM:{id}:{token}
     */
    public function getQrContentAttribute(): string
    {
        return "REM:{$this->id}:{$this->qr_token}";
    }

    /**
     * Get formatted delivery date.
     */
    public function getDeliveredAtFormattedAttribute(): ?string
    {
        return $this->delivered_at?->format('d M Y H:i');
    }

    /**
     * Get formatted quantity with UOM.
     */
    public function getQtyWithUomAttribute(): string
    {
        if (! $this->qty_remaining) {
            return '-';
        }

        return number_format($this->qty_remaining, 2).' '.($this->uom ?? '');
    }

    // ==================== SCOPES ====================

    public function scopeForEvidenceUnit($query, int $evidenceUnitId)
    {
        return $query->where('evidence_unit_id', $evidenceUnitId);
    }
}
