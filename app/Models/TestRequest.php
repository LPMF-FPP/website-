<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class TestRequest extends Model
{
    use HasFactory;

    protected $fillable = [

        'request_number', 'receipt_number', 'investigator_id', 'user_id', 'to_office', 'suspect_name',

        'suspect_gender', 'suspect_age', 'suspect_address', 'case_number', 'case_description', 'incident_date',

        'incident_location', 'status', 'official_letter_path', 'evidence_photo_path',

        'submitted_at', 'verified_at', 'received_at', 'completed_at',
        'rejected_reason', 'rejected_at', 'rejected_by',

    ];

    protected $casts = [

        'incident_date' => 'date',

        'suspect_age' => 'integer',

        'submitted_at' => 'datetime',

        'verified_at' => 'datetime',

        'received_at' => 'datetime',

        'completed_at' => 'datetime',
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
                $model->request_number = $numbering->issue('ba', [
                    'investigator_id' => $model->investigator_id ?? null,
                ]);
            }

            // Generate receipt/tracking number (nomor resi)
            // Same principle - single call, no retry needed
            if (! $model->receipt_number) {
                $model->receipt_number = $numbering->issue('tracking', [
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

    public function evidenceUnits(): HasMany
    {
        return $this->hasMany(EvidenceUnit::class, 'request_id');
    }

    public function suspects(): HasMany
    {
        return $this->hasMany(Suspect::class)->orderBy('order_no');
    }
}
