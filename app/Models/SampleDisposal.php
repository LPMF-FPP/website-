<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SampleDisposalMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SampleDisposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'executed_at',
        'method',
        'witness_name',
        'witness_role',
        'witness_user_id',
        'notes',
        'executed_by',
        'executed_by_name',
        'executed_by_role',
        'created_by',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'method' => SampleDisposalMethod::class,
    ];

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class, 'disposal_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function witnessUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_user_id');
    }

    public static function generateBatchNumber(): string
    {
        $year = now()->year;
        $prefix = "DSP-{$year}-";

        $lastNumber = static::query()
            ->where('batch_number', 'like', $prefix.'%')
            ->orderByDesc('batch_number')
            ->value('batch_number');

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $prefix.str_pad((string) $newSequence, 4, '0', STR_PAD_LEFT);
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->method->label();
    }

    public function getSampleCountAttribute(): int
    {
        return $this->samples()->count();
    }
}
