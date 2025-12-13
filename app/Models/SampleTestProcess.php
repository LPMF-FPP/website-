<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleTestProcess extends Model
{
    use HasFactory;

    protected $fillable = [
        'sample_id',
        'stage',
        'performed_by',
        'started_at',
        'completed_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'stage' => \App\Enums\TestProcessStage::class
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function getStageLabelAttribute(): string
    {
        return $this->stage->label();
    }
}
