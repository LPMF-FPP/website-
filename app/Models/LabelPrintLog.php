<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LabelPrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'label_type',
        'printable_type',
        'printable_id',
        'printed_by',
        'print_reason',
        'print_format',
        'print_count',
    ];

    protected $casts = [
        'print_count' => 'integer',
    ];

    public const LABEL_TYPES = [
        'evidence' => 'Label Barang Bukti',
        'remaining' => 'Label Sisa Sampel',
    ];

    public const PRINT_REASONS = [
        'first_print' => 'Cetak pertama',
        'damaged' => 'Rusak',
        'lost' => 'Hilang',
        'faded' => 'Pudar',
        'updated' => 'Data diperbarui',
        'other' => 'Lainnya',
    ];

    public const PRINT_FORMATS = [
        'a4' => 'A4 Sheet',
        'single' => 'Single Label',
    ];

    // ==================== RELATIONSHIPS ====================

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    // ==================== ACCESSORS ====================

    public function getLabelTypeLabelAttribute(): string
    {
        return self::LABEL_TYPES[$this->label_type] ?? $this->label_type;
    }

    public function getPrintReasonLabelAttribute(): ?string
    {
        return self::PRINT_REASONS[$this->print_reason] ?? $this->print_reason;
    }

    public function getPrintFormatLabelAttribute(): string
    {
        return self::PRINT_FORMATS[$this->print_format] ?? $this->print_format;
    }

    // ==================== STATIC METHODS ====================

    /**
     * Log a print action.
     */
    public static function logPrint(
        string $labelType,
        Model $printable,
        int $userId,
        string $format = 'a4',
        ?string $reason = null,
        int $count = 1
    ): self {
        return self::create([
            'label_type' => $labelType,
            'printable_type' => get_class($printable),
            'printable_id' => $printable->id,
            'printed_by' => $userId,
            'print_format' => $format,
            'print_reason' => $reason ?? 'first_print',
            'print_count' => $count,
        ]);
    }
}
