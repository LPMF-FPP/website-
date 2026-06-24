<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TestRequest extends Model
{
    use HasFactory;

    protected $fillable = [

        'request_number', 'receipt_number', 'investigator_id', 'user_id', 'suspect_name',

        'suspect_gender', 'suspect_age', 'suspect_address', 'case_number', 'letter_date', 'case_description', 'incident_date',

        'incident_location', 'status', 'official_letter_path', 'evidence_photo_path',
        'has_expert_witness_request', 'expert_witness_letter_number', 'expert_witness_letter_date',

        'submitted_at', 'verified_at', 'received_at', 'completed_at',
        'ready_for_delivery_at',
        'rejected_reason', 'rejected_at', 'rejected_by',

    ];

    protected $casts = [

        'incident_date' => 'date',
        'letter_date' => 'date',
        'expert_witness_letter_date' => 'date',
        'has_expert_witness_request' => 'boolean',

        'suspect_age' => 'integer',

        'submitted_at' => 'datetime',

        'verified_at' => 'datetime',

        'received_at' => 'datetime',

        'completed_at' => 'datetime',
        'ready_for_delivery_at' => 'datetime',
        'rejected_at' => 'datetime',

    ];

    protected static function boot()
    {

        parent::boot();

        static::creating(function ($model) {
            $numbering = app(\App\Services\NumberingService::class);

            // Generate Berita Acara (BA) number for the request
            // NumberingService::issue() uses lockForUpdate() which guarantees uniqueness
            // DO NOT use retry loops - they cause sequence gaps when transactions fail
            if (! $model->request_number) {
                $model->request_number = self::issueUniqueNumber($numbering, 'ba', 'request_number', [
                    'investigator_id' => $model->investigator_id ?? null,
                ]);
            }

            // Generate receipt/tracking number (nomor resi)
            // Same principle - single call, no retry needed
            if (! $model->receipt_number) {
                $model->receipt_number = self::issueUniqueNumber($numbering, 'tracking', 'receipt_number', [
                    'investigator_id' => $model->investigator_id ?? null,
                ]);
            }
        });

        static::deleted(function (self $model) {
            // Attempt to rollback numbering sequences
            // Only succeeds if this is the LAST number issued (prevents gaps on delete)
            $numbering = app(\App\Services\NumberingService::class);

            // Rollback BA number
            if ($model->request_number) {
                $numbering->rollback('ba', $model->request_number, [
                    'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
                    'investigator_id' => $model->investigator_id,
                ]);
                // Clear cache
                Cache::forget('track:condensed:'.$model->request_number);
            }

            // Rollback tracking/receipt number
            if ($model->receipt_number) {
                $numbering->rollback('tracking', $model->receipt_number, [
                    'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
                    'investigator_id' => $model->investigator_id,
                ]);
                // Clear cache
                Cache::forget('track:condensed:'.$model->receipt_number);
            }
        });

        $clear = function (self $model) {
            // Clear cache for both request_number and receipt_number
            if ($model->request_number) {
                Cache::forget('track:condensed:'.$model->request_number);
            }
            if ($model->receipt_number) {
                Cache::forget('track:condensed:'.$model->receipt_number);
            }
        };

        static::saved($clear);

    }

    private static function issueUniqueNumber(\App\Services\NumberingService $numbering, string $scope, string $column, array $context): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $number = $numbering->issue($scope, $context);

            if (! self::query()->where($column, $number)->exists()) {
                return $number;
            }
        }

        throw new \RuntimeException("Gagal menerbitkan nomor unik untuk {$column}.");
    }

    public function investigator(): BelongsTo
    {

        return $this->belongsTo(Investigator::class);

    }

    public function samples(): HasMany
    {

        return $this->hasMany(Sample::class);

    }

    public function testProcesses(): HasManyThrough
    {
        return $this->hasManyThrough(
            SampleTestProcess::class,
            Sample::class,
            'test_request_id',
            'sample_id'
        );
    }

    public function recentViews(): HasMany
    {
        return $this->hasMany(RecentRequest::class, 'test_request_id');
    }

    public function user(): BelongsTo
    {

        return $this->belongsTo(User::class);

    }

    public function documents(): HasMany
    {

        return $this->hasMany(Document::class);

    }

    public function surveyResponses(): HasMany
    {

        return $this->hasMany(SurveyResponse::class);

    }

    public function customerSurvey(): HasOne
    {
        return $this->hasOne(CustomerSurvey::class);
    }

    public function getProcessingWorkingDaysAttribute(): ?int
    {
        $startDate = $this->submitted_at ?? $this->created_at;
        $endDate = $this->ready_for_delivery_at;

        if (! $startDate || ! $endDate) {
            return null;
        }

        if ($endDate->lt($startDate)) {
            return 0;
        }

        return (int) $startDate->diffInWeekdays($endDate);
    }

    public function getDisplaySuspectNamesAttribute(): array
    {
        if ($this->relationLoaded('suspects') && $this->suspects->isNotEmpty()) {
            return $this->suspects
                ->pluck('name')
                ->filter(fn ($name) => filled($name))
                ->values()
                ->all();
        }

        if (filled($this->suspect_name)) {
            return [(string) $this->suspect_name];
        }

        return [];
    }

    public function getDisplayPrimarySuspectNameAttribute(): ?string
    {
        return $this->display_suspect_names[0] ?? null;
    }

    public function getDisplayAdditionalSuspectCountAttribute(): int
    {
        return max(count($this->display_suspect_names) - 1, 0);
    }

    public function getDisplaySuspectCollectionAttribute(): Collection
    {
        if ($this->relationLoaded('suspects') && $this->suspects->isNotEmpty()) {
            return $this->suspects->filter(fn (Suspect $suspect) => filled($suspect->name))->values();
        }

        if (! filled($this->suspect_name)) {
            return collect();
        }

        return collect([
            new Suspect([
                'name' => $this->suspect_name,
                'gender' => $this->suspect_gender,
                'age' => $this->suspect_age,
                'order_no' => 1,
            ]),
        ]);
    }

    public function evidenceUnits(): HasMany
    {
        return $this->hasMany(EvidenceUnit::class, 'request_id');
    }

    public function suspects(): HasMany
    {
        return $this->hasMany(Suspect::class)->orderBy('order_no');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class, 'request_id');
    }
}
