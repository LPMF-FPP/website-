<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentLocation;
use App\Models\InstrumentAsset;
use App\Models\Sample;
use App\Services\DocumentService;
use App\Services\EnvironmentMonitoringService;
use App\Services\InstrumentLoggingService;
use App\Services\PdfRenderService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MonthlyLogReportController extends Controller
{
    public function __construct(
        protected EnvironmentMonitoringService $environmentService,
        protected InstrumentLoggingService $instrumentService,
        protected PdfRenderService $pdfService,
        protected DocumentService $documentService
    ) {}

    public function index(Request $request)
    {
        $locations = EnvironmentLocation::orderBy('name')->get();
        $assets = InstrumentAsset::with('instrument')
            ->orderBy('asset_code')
            ->get();

        return view('reports.monthly-logs', [
            'locations' => $locations,
            'assets' => $assets,
        ]);
    }

    public function environmentReport(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'exists:environment_locations,id'],
            'month' => ['required', 'date_format:Y-m'],
            'save' => ['nullable', 'boolean'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        $year = $month->year;
        $monthNum = $month->month;

        $locationId = $validated['location_id'] ?? null;
        $location = $locationId ? EnvironmentLocation::find($locationId) : null;

        if ($locationId) {
            $readings = $this->environmentService->getReadingsForMonth($locationId, $year, $monthNum);
        } else {
            $readings = collect();
            $allLocations = EnvironmentLocation::orderBy('name')->get();
            foreach ($allLocations as $loc) {
                $locReadings = $this->environmentService->getReadingsForMonth($loc->id, $year, $monthNum);
                foreach ($locReadings as $reading) {
                    $reading->location_name = $loc->name;
                    $readings->push($reading);
                }
            }
            $readings = $readings->sortBy('measured_at');
        }

        $html = view('pdf.environment-monthly', [
            'location' => $location,
            'readings' => $readings,
            'month' => $month,
            'generatedAt' => Carbon::now(),
        ])->render();

        $pdf = $this->pdfService->htmlToPdf($html);

        $filename = $location
            ? "log_suhu_lingkungan_{$location->name}_{$month->format('Y_m')}.pdf"
            : "log_suhu_lingkungan_semua_{$month->format('Y_m')}.pdf";

        // Save to Documents if requested
        if ($request->boolean('save')) {
            $baseName = $location
                ? "Log-Suhu-{$location->name}-{$month->format('Y-m')}"
                : "Log-Suhu-Semua-{$month->format('Y-m')}";

            $this->documentService->storeStandaloneReport(
                binary: $pdf,
                ext: 'pdf',
                type: 'environment_monthly_log',
                baseName: $baseName,
                metadata: [
                    'month' => $validated['month'],
                    'location_id' => $locationId,
                    'generated_by' => auth()->id(),
                ]
            );
        }

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$filename}\"");
    }

    public function instrumentReport(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => ['nullable', 'exists:instrument_assets,id'],
            'month' => ['required', 'date_format:Y-m'],
            'save' => ['nullable', 'boolean'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        $year = $month->year;
        $monthNum = $month->month;

        $assetId = $validated['asset_id'] ?? null;
        $asset = $assetId ? InstrumentAsset::with('instrument')->find($assetId) : null;

        $logs = $this->instrumentService->getUsageLogsForMonth($year, $monthNum, $assetId);

        $html = view('pdf.instrument-monthly', [
            'asset' => $asset,
            'logs' => $logs,
            'month' => $month,
            'generatedAt' => Carbon::now(),
        ])->render();

        $pdf = $this->pdfService->htmlToPdf($html);

        $filename = $asset
            ? "log_instrumen_{$asset->asset_code}_{$month->format('Y_m')}.pdf"
            : "log_instrumen_semua_{$month->format('Y_m')}.pdf";

        // Save to Documents if requested
        if ($request->boolean('save')) {
            $baseName = $asset
                ? "Log-Instrumen-{$asset->asset_code}-{$month->format('Y-m')}"
                : "Log-Instrumen-Semua-{$month->format('Y-m')}";

            $this->documentService->storeStandaloneReport(
                binary: $pdf,
                ext: 'pdf',
                type: 'instrument_monthly_log',
                baseName: $baseName,
                metadata: [
                    'month' => $validated['month'],
                    'asset_id' => $assetId,
                    'generated_by' => auth()->id(),
                ]
            );
        }

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$filename}\"");
    }

    public function weighingReport(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'save' => ['nullable', 'boolean'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $samples = Sample::where(function ($query) use ($startOfMonth, $endOfMonth) {
            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereNotNull('weighed_mass_value')
                    ->whereBetween('weighed_at', [$startOfMonth, $endOfMonth]);
            })->orWhere(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereNotNull('uvvis_weighed_grams')
                    ->whereNull('weighed_mass_value')
                    ->whereBetween('uvvis_weighed_at', [$startOfMonth, $endOfMonth]);
            });
        })
            ->with(['testRequest', 'uvvisWeighedBy', 'weighedByUser'])
            ->orderByRaw('COALESCE(weighed_at, uvvis_weighed_at)')
            ->get();

        $html = view('pdf.weighing-monthly', [
            'samples' => $samples,
            'month' => $month,
            'generatedAt' => Carbon::now(),
        ])->render();

        $pdf = $this->pdfService->htmlToPdf($html);

        $filename = "log_penimbangan_{$month->format('Y_m')}.pdf";

        if ($request->boolean('save')) {
            $baseName = "Log-Penimbangan-{$month->format('Y-m')}";

            $this->documentService->storeStandaloneReport(
                binary: $pdf,
                ext: 'pdf',
                type: 'weighing_monthly_log',
                baseName: $baseName,
                metadata: [
                    'month' => $validated['month'],
                    'generated_by' => auth()->id(),
                ]
            );
        }

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$filename}\"");
    }
}
