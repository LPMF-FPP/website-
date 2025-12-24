<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Cache;



class TestRequest extends Model
{
    use HasFactory;

    protected $fillable = [

        'request_number', 'receipt_number', 'investigator_id', 'user_id', 'to_office', 'suspect_name',

        'suspect_gender', 'suspect_age', 'suspect_address', 'case_number', 'case_description', 'incident_date',

        'incident_location', 'status', 'official_letter_path', 'evidence_photo_path',

        'submitted_at', 'verified_at', 'received_at', 'completed_at'

    ];



    protected $casts = [

        'incident_date' => 'date',

        'suspect_age' => 'integer',

        'submitted_at' => 'datetime',

        'verified_at' => 'datetime',

        'received_at' => 'datetime',

        'completed_at' => 'datetime',

    ];



    protected static function boot()

    {

        parent::boot();



        static::creating(function ($model) {
            $numbering = app(\App\Services\NumberingService::class);
            
            if (!$model->request_number) {
                // Generate Berita Acara (BA) number for the request
                $model->request_number = $numbering->issue('ba', [
                    'investigator_id' => $model->investigator_id ?? null,
                ]);
            }
            
            if (!$model->receipt_number) {
                // Generate receipt/tracking number (nomor resi)
                $model->receipt_number = $numbering->issue('tracking', [
                    'investigator_id' => $model->investigator_id ?? null,
                ]);
            }
        });



        $clear = function (self $model) {
            // Clear cache for both request_number and receipt_number
            if ($model->request_number) {
                Cache::forget('track:condensed:' . $model->request_number);
            }
            if ($model->receipt_number) {
                Cache::forget('track:condensed:' . $model->receipt_number);
            }
        };

        static::saved($clear);

        static::deleted($clear);

    }



    /**
     * @deprecated Use NumberingService instead
     * Legacy method - kept for reference only
     */
    protected static function generateRequestNumber(): string
    {
        // This method is no longer used.
        // Request numbers (BA Penerimaan) are now generated via NumberingService
        // which uses settings from /settings page
        
        throw new \RuntimeException(
            'generateRequestNumber() is deprecated. Use NumberingService::issue() instead.'
        );
    }



    public function investigator(): BelongsTo

    {

        return $this->belongsTo(Investigator::class);

    }



    public function samples(): HasMany

    {

        return $this->hasMany(Sample::class);

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

}

