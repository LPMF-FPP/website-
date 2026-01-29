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

    public function exportEnvironmentCsv(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'exists:environment_locations,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        $year = $month->year;
        $monthNum = $month->month;
        $locationId = $validated['location_id'] ?? null;

        $location = $locationId ? EnvironmentLocation::find($locationId) : null;
        $filename = $location
            ? "log_suhu_{$location->name}_{$month->format('Y_m')}.csv"
            : "log_suhu_semua_{$month->format('Y_m')}.csv";

        return response()->streamDownload(function () use ($locationId, $year, $monthNum) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM for Excel UTF-8

            // Header
            fputcsv($handle, [
                'Tanggal', 'Waktu', 'Lokasi', 'Suhu (°C)', 'Kelembaban (%RH)', 'Pencatat', 'Sumber', 'Catatan',
            ], ';');

            if ($locationId) {
                $readings = $this->environmentService->getReadingsForMonth($locationId, $year, $monthNum);
                $this->writeEnvironmentRows($handle, $readings);
            } else {
                $allLocations = EnvironmentLocation::orderBy('name')->get();
                foreach ($allLocations as $loc) {
                    $readings = $this->environmentService->getReadingsForMonth($loc->id, $year, $monthNum);
                    // Add location name to objects for writing
                    foreach ($readings as $reading) {
                        $reading->location_name = $loc->name;
                    }
                    $this->writeEnvironmentRows($handle, $readings, true);
                }
            }

            fclose($handle);
        }, $filename);
    }

    private function writeEnvironmentRows($handle, $readings, $includeLocation = false)
    {
        foreach ($readings as $reading) {
            $locationName = $includeLocation ? ($reading->location_name ?? '-') : ($reading->location->name ?? '-');

            fputcsv($handle, [
                $reading->measured_at->format('Y-m-d'),
                $reading->measured_at->format('H:i'),
                $locationName,
                $reading->temperature_c,
                $reading->humidity_rh ?? '-',
                $reading->enteredBy->name ?? 'System',
                $reading->source->label(),
                $reading->notes,
            ], ';');
        }
    }

    public function exportInstrumentCsv(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => ['nullable', 'exists:instrument_assets,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        $year = $month->year;
        $monthNum = $month->month;
        $assetId = $validated['asset_id'] ?? null;

        $asset = $assetId ? InstrumentAsset::find($assetId) : null;
        $filename = $asset
            ? "log_instrumen_{$asset->asset_code}_{$month->format('Y_m')}.csv"
            : "log_instrumen_semua_{$month->format('Y_m')}.csv";

        return response()->streamDownload(function () use ($year, $monthNum, $assetId) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM for Excel

            // Header
            fputcsv($handle, [
                'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Nama Instrumen', 'Kode Aset', 'Pengguna', 'Sampel/Proyek', 'Kondisi Akhir', 'Catatan',
            ], ';');

            $logs = $this->instrumentService->getUsageLogsForMonth($year, $monthNum, $assetId);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->used_at->format('Y-m-d'),
                    $log->start_time ? Carbon::parse($log->start_time)->format('H:i') : '-',
                    $log->end_time ? Carbon::parse($log->end_time)->format('H:i') : '-',
                    $log->asset->instrument->name ?? '-',
                    $log->asset->asset_code ?? '-',
                    $log->performedBy->name ?? '-',
                    $log->sample_name ?? '-',
                    $log->condition_after ?? '-',
                    $log->notes,
                ], ';');
            }

            fclose($handle);
        }, $filename);
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
