<?php

namespace App\Models;

use App\Enums\InstrumentUsageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MethodInstrumentRequirement extends Model
{
    public const AVAILABLE_METHODS = [
        'uv_vis',
        'gc_ms',
        'lc_ms',
    ];

    protected $fillable = [
        'method_code',
        'instrument_id',
        'mandatory',
        'usage_type',
        'sequence',
    ];

    protected $casts = [
        'mandatory' => 'boolean',
        'usage_type' => InstrumentUsageType::class,
        'sequence' => 'integer',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function scopeForMethod($query, string $methodCode)
    {
        return $query->where('method_code', $methodCode);
    }

    public function scopeMandatory($query)
    {
        return $query->where('mandatory', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }
}
