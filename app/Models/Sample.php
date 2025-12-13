<?php



namespace App\Models;

use App\Enums\SampleStatus;
use App\Enums\TestProcessStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;



class Sample extends Model
{
    use HasFactory;



    public const OTHER_SAMPLE_CATEGORIES = [



        'obat' => 'Obat',



        'suplemen_jamu' => 'Suplemen/Jamu',



        'kosmetik' => 'Kosmetik',



        'makanan_minuman' => 'Makanan/Minuman',



    ];



    protected $fillable = [

        'test_request_id',
        'sample_code',
        'sample_name',
        'sample_description',
        'sample_form',
        'sample_category',
        'sample_color',
        'sample_weight',
        'package_quantity',
        'net_weight',
        'packaging_type',
        'storage_location',
        'condition',
        'photo_path',
        'receipt_path',
        'received_by',
        'received_at',
        'sample_status',
        'test_methods',
        'active_substance',
        'testing_notes',
        'tested_by',
        'testing_started_at',
        'testing_completed_at',
        'other_sample_category',
        'physical_identification',
        'quantity',
        'quantity_unit',
        'batch_number',
        'expiry_date',
        'assigned_analyst_id',
        'test_date',
        'test_type',
        'notes',
        'status',



    ];



    protected $casts = [
        // Temporary disabled for testing - enum mismatch with database
        // 'sample_status' => \App\Enums\SampleStatus::class,
        // 'status' => \App\Enums\SampleStatus::class,
        'sample_weight' => 'decimal:2',
        'quantity' => 'decimal:2',
        'package_quantity' => 'integer',
        // 'test_methods' => 'array',  // Temporary disabled
        'expiry_date' => 'date',
        'test_date' => 'date',
        'received_at' => 'datetime',
        'testing_started_at' => 'datetime',
        'testing_completed_at' => 'datetime',
    ];



    protected static function boot()



    {



        parent::boot();



        static::creating(function ($model) {



            if (!$model->sample_code) {



                $model->sample_code = static::generateSampleCode();



            }



        });



    }



    protected static function generateSampleCode(): string

    {

        $now = now();

        $year = $now->year;

        $romanMonth = static::toRoman($now->month);



        return DB::transaction(function () use ($year, $romanMonth) {

            $baseQuery = static::whereYear('created_at', $year);



            $latest = (clone $baseQuery)

                ->orderByDesc('id')

                ->lockForUpdate()

                ->first();



            if ($latest && preg_match('/^W(?P<number>\d+)/', $latest->sample_code, $matches)) {

                $sequence = (int) $matches['number'] + 1;

            } else {

                $sequence = (clone $baseQuery)->count() + 1;

            }



            return sprintf('W%03d%s%d', $sequence, $romanMonth, $year);

        });

    }



    protected static function toRoman(int $month): string



    {



        $map = [



            1 => 'I',



            2 => 'II',



            3 => 'III',



            4 => 'IV',



            5 => 'V',



            6 => 'VI',



            7 => 'VII',



            8 => 'VIII',



            9 => 'IX',



            10 => 'X',



            11 => 'XI',



            12 => 'XII',



        ];



        return $map[$month] ?? 'I';



    }



    public function testRequest(): BelongsTo



    {



        return $this->belongsTo(TestRequest::class);



    }



    public function testResult(): HasOne



    {



        return $this->hasOne(TestResult::class);



    }





    public function testProcesses(): HasMany





    {





        return $this->hasMany(SampleTestProcess::class);





    }



    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_analyst_id');
    }

    public function getCurrentTestProcess(): ?SampleTestProcess
    {
        return $this->testProcesses()
            ->whereNull('completed_at')
            ->whereNotNull('started_at')
            ->first();
    }

    public function getLastCompletedProcess(): ?SampleTestProcess
    {
        return $this->testProcesses()
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();
    }

    public function isReadyForNextStage(): bool
    {
        return !$this->getCurrentTestProcess() &&
               in_array($this->status, [
                   SampleStatus::ADMIN_PENDING,
                   SampleStatus::PREPARATION_PENDING,
                   SampleStatus::INSTRUMENTATION_PENDING,
                   SampleStatus::INTERPRETATION_PENDING
               ]);
    }

    public function canStartStage(TestProcessStage $stage): bool
    {
        return $this->status === $stage->getRequiredStatus() &&
               !$this->getCurrentTestProcess();
    }

    public function getSampleTypeLabelAttribute(): string
    {



        if ($this->sample_type !== 'other') {



            return ucfirst($this->sample_type);



        }



        if (!$this->other_sample_category) {



            return 'Other';



        }



        $label = self::OTHER_SAMPLE_CATEGORIES[$this->other_sample_category]



            ?? ucwords(str_replace('_', ' ', $this->other_sample_category));



        return 'Other - ' . $label;



    }



}

