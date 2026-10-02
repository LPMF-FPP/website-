<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsolidatedReportRequest;
use App\Models\ConsolidatedReport;
use App\Models\SystemSetting;
use App\Services\ConsolidatedReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConsolidatedReportController extends Controller
{
    public function __construct(
        private readonly ConsolidatedReportService $reportService
    ) {}

    public function index()
    {
        $this->authorize('statistik.export');

        // This will be handled in the main StatisticsController@index view with a tab
        // But if accessed directly/via XHR for tab content, we can return partial
        if (request()->ajax()) {
            $defaultSigners = $this->reportService->getDefaultSigners();

            return view('statistics.partials.consolidated-form', compact('defaultSigners'));
        }

        return redirect()->route('statistics.index', ['tab' => 'reports']);
    }

    public function preview(Request $request)
    {
        $this->authorize('statistik.export');

        $request->validate([
            'period_type' => ['required', 'in:biweekly,monthly,quarterly'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]);

        $data = $this->reportService->getPreviewData(
            $request->period_type,
            Carbon::parse($request->period_start),
            Carbon::parse($request->period_end)
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(ConsolidatedReportRequest $request)
    {
        try {
            $report = $this->reportService->generate(
                $request->validated(),
                $request->user()->id
            );

            // Send WhatsApp notification
            $this->reportService->sendGenerationNotification($report, true);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil di-generate',
                'data' => [
                    'id' => $report->id,
                    'period_label' => $report->period_label,
                    'download_url' => $report->download_url,
                ],
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Periksa kembali periode dan data yang dikirim.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Report generation failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat laporan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function revise(Request $request, ConsolidatedReport $report)
    {
        $this->authorize('statistik.export');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Tuliskan alasan koreksi laporan.',
            'reason.min' => 'Alasan revisi minimal 10 karakter.',
            'reason.max' => 'Alasan revisi maksimal 2.000 karakter.',
        ]);

        $revision = $this->reportService->revise($report, $validated['reason'], (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Revisi laporan berhasil diterbitkan.',
            'data' => [
                'id' => $revision->id,
                'period_label' => $revision->period_label,
                'revision_number' => $revision->revision_number,
                'download_url' => $revision->download_url,
            ],
        ], 201);
    }

    public function download(ConsolidatedReport $report)
    {
        $this->authorize('statistik.export');

        if (! $report->pdf_path || ! Storage::exists($report->pdf_path)) {
            // Attempt regeneration if file missing
            try {
                $this->reportService->generatePdf($report);
            } catch (\Exception $e) {
                return back()->with('error', 'File PDF tidak ditemukan dan gagal di-generate ulang.');
            }
        }

        return Storage::download($report->pdf_path);
    }

    public function destroy(ConsolidatedReport $report)
    {
        $this->authorize('statistik.export');

        if ($report->revision_number > 1 || $report->revisions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan yang sudah menjadi bagian dari riwayat revisi tidak dapat dihapus.',
            ], 409);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dihapus',
        ]);
    }

    public function history()
    {
        $this->authorize('statistik.export');

        $reports = ConsolidatedReport::with('generatedBy')
            ->withCount('revisions')
            ->orderByDesc('generated_at')
            ->paginate(10);

        return response()->json($reports);
    }

    public function saveDefaultSigners(Request $request)
    {
        $this->authorize('statistik.export');

        $request->validate([
            'signers' => ['required', 'array'],
            'signers.*.role' => ['required', 'string'],
            'signers.*.name' => ['nullable', 'string', 'max:255'],
            'signers.*.position' => ['nullable', 'string', 'max:255'],
            'signers.*.nip' => ['nullable', 'string', 'max:50'],
        ]);

        $setting = SystemSetting::where('key', 'consolidated_report.default_signers')->first();
        $oldValue = $setting ? $setting->value : null;

        SystemSetting::updateOrCreate(
            ['key' => 'consolidated_report.default_signers'],
            ['value' => $request->signers]
        );

        \Log::info('Default signers updated by user '.$request->user()->id, [
            'old' => $oldValue,
            'new' => $request->signers,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Default penandatangan berhasil disimpan.',
        ]);
    }
}
