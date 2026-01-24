<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_request_id',
        'respondent_name',
        'respondent_institution',
        'respondent_job_category',
        'request_type',
        'voluntary_statement',
        'answers',
        'suggestion',
        'complaint',
        'follow_up',
        'score_avg',
        'submitted_at',
        'submitted_by',
    ];

    protected $casts = [
        'answers' => 'array',
        'voluntary_statement' => 'boolean',
        'submitted_at' => 'datetime',
        'score_avg' => 'decimal:2',
    ];

    public function testRequest(): BelongsTo
    {
        return $this->belongsTo(TestRequest::class);
    }

    public function isComplete(): bool
    {
        $requiredFields = [
            $this->respondent_name,
            $this->respondent_institution,
            $this->respondent_job_category,
            $this->request_type,
            $this->suggestion,
        ];

        foreach ($requiredFields as $value) {
            if ($value === null || trim((string) $value) === '') {
                return false;
            }
        }

        if ($this->voluntary_statement !== true) {
            return false;
        }

        $answers = $this->answers ?? [];

        $surveyService = app(\App\Services\SurveyQuestionService::class);
        $activeQuestions = $surveyService->getQuestions();
        $requiredKeys = collect($activeQuestions)->pluck('key')->toArray();

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $answers)) {
                return false;
            }
            $value = $answers[$key];
            if (! is_numeric($value)) {
                return false;
            }
            $intValue = (int) $value;
            // Basic validation > 0, upper limit handled by form
            if ($intValue < 1) {
                return false;
            }
        }

        return true;
    }
}
