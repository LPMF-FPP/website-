<?php

namespace App\Models;

use App\Enums\InstrumentUsageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'test_request_id',
        'sample_id',
        'method_code',
        'instrument_asset_id',
        'usage_type',
        'logged_at',
        'performed_by',
        'notes',
    ];

    protected $casts = [
        'usage_type' => InstrumentUsageType::class,
        'logged_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function instrumentAsset(): BelongsTo
    {
        return $this->belongsTo(InstrumentAsset::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForSample($query, int $sampleId)
    {
        return $query->where('sample_id', $sampleId);
    }

    public function scopeForMethod($query, string $methodCode)
    {
        return $query->where('method_code', $methodCode);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('logged_at', $year)
            ->whereMonth('logged_at', $month);
    }
}
