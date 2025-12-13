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

        'request_number', 'investigator_id', 'user_id', 'to_office', 'suspect_name',

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

            if (!$model->request_number) {

                $model->request_number = static::generateRequestNumber();

            }

        });



        $clear = function (self $model) {

            if ($model->request_number) {

                Cache::forget('track:condensed:' . $model->request_number);

            }

        };

        static::saved($clear);

        static::deleted($clear);

    }



    protected static function generateRequestNumber(): string

    {

        $year = now()->year;



        return DB::transaction(function () use ($year) {

            $latest = static::whereYear('created_at', $year)

                ->lockForUpdate()

                ->orderByDesc('request_number')

                ->first();



            $sequence = $latest

                ? (int) substr($latest->request_number, -4) + 1

                : 1;



            return sprintf('REQ-%s-%04d', $year, $sequence);

        });

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

