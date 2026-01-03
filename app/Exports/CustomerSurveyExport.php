<?php

namespace App\Exports;

use App\Models\CustomerSurvey;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomerSurveyExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Carbon $startDate, private Carbon $endDate) {}

    public function query()
    {
        return CustomerSurvey::query()
            ->with(['testRequest.investigator'])
            ->whereBetween('submitted_at', [$this->startDate, $this->endDate])
            ->orderBy('submitted_at');
    }

    public function headings(): array
    {
        return [
            'submitted_at',
            'request_id',
            'request_number_resi',
            'investigator_name',
            'respondent_name',
            'respondent_job_title',
            'respondent_institution',
            'respondent_job_category',
            'request_type',
            'q_persyaratan',
            'q_prosedur',
            'q_ketepatan_waktu',
            'q_kesesuaian_hasil',
            'q_kompetensi',
            'q_sikap',
            'q_pengaduan',
            'q_fasilitas',
            'score_avg',
            'complaint',
            'follow_up',
            'suggestion',
        ];
    }

    public function map($survey): array
    {
        $request = $survey->testRequest;
        $answers = $survey->answers ?? [];

        return [
            $survey->submitted_at?->format('Y-m-d H:i:s'),
            $survey->test_request_id,
            $request?->receipt_number ?: $request?->request_number,
            $request?->investigator?->name,
            $survey->respondent_name,
            $survey->respondent_job_title,
            $survey->respondent_institution,
            $survey->respondent_job_category,
            $survey->request_type,
            $answers['persyaratan'] ?? null,
            $answers['prosedur'] ?? null,
            $answers['ketepatan_waktu'] ?? null,
            $answers['kesesuaian_hasil'] ?? null,
            $answers['kompetensi'] ?? null,
            $answers['sikap'] ?? null,
            $answers['pengaduan'] ?? null,
            $answers['fasilitas'] ?? null,
            $survey->score_avg,
            $survey->complaint,
            $survey->follow_up,
            $survey->suggestion,
        ];
    }
}
