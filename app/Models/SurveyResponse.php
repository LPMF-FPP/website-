<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_request_id', 'service_quality', 'process_speed',
        'staff_professionalism', 'facility_condition',
        'overall_satisfaction', 'suggestions', 'complaints',
        'additional_comments', 'respondent_name', 'respondent_contact'
    ];

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function getAverageRatingAttribute(): float
    {
        return ($this->service_quality + $this->process_speed +
                $this->staff_professionalism + $this->facility_condition +
                $this->overall_satisfaction) / 5;
    }
}
