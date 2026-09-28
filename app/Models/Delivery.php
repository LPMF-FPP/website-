<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_id',
        'delivered_by',
        'notes',
        'delivery_date',
        'status',
        'handover_cycle',
        'collected_at',
        'has_surat_pengantar',
        'surat_pengantar_number',
        'surat_pengantar_date',
    ];

    protected $casts = [
        'delivery_date' => 'datetime',
        'collected_at' => 'datetime',
        'status' => \App\Enums\DeliveryStatus::class,
        'handover_cycle' => 'integer',
        'has_surat_pengantar' => 'boolean',
        'surat_pengantar_date' => 'date',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class, 'request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function reopenings(): HasMany
    {
        return $this->hasMany(DeliveryReopening::class)->orderBy('handover_cycle');
    }

    public function hasBeenCollected(): bool
    {
        return $this->collected_at !== null
            || $this->status === \App\Enums\DeliveryStatus::COLLECTED;
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function markAsCollected(): void
    {
        $this->status = \App\Enums\DeliveryStatus::COLLECTED;
        $this->collected_at = now();
        $this->save();
    }

    public function markAsUncollected(): void
    {
        $this->status = \App\Enums\DeliveryStatus::UNCOLLECTED;
        $this->save();
    }

    public function isReadyForDelivery(): bool
    {
        return $this->request->samples()
            ->whereHas('testProcesses', function ($query) {
                $query->whereNotNull('completed_at')
                    ->whereIn('stage', ['preparation', 'instrumentation', 'interpretation'])
                    ->groupBy('sample_id')
                    ->havingRaw('COUNT(DISTINCT stage) = ?', [3]);
            })
            ->exists();
    }
}
