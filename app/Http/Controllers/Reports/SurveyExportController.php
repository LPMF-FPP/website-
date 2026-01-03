<?php

namespace App\Http\Controllers\Reports;

use App\Exports\CustomerSurveyExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SurveyExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ], [
            'start.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'end.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'end.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);

        $startDate = isset($validated['start'])
            ? Carbon::createFromFormat('Y-m-d', $validated['start'])->startOfDay()
            : now()->startOfMonth();

        $endDate = isset($validated['end'])
            ? Carbon::createFromFormat('Y-m-d', $validated['end'])->endOfDay()
            : now()->endOfMonth();

        $filename = sprintf(
            'survey_kepuasan_%s_%s.xlsx',
            $startDate->format('Ymd'),
            $endDate->format('Ymd')
        );

        return Excel::download(new CustomerSurveyExport($startDate, $endDate), $filename);
    }
}
