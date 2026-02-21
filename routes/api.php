<?php

use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentDeleteController;
use App\Http\Controllers\Api\DocumentDownloadController;
use App\Http\Controllers\Api\JobStatusController;
use App\Http\Controllers\Api\PeopleController;
use App\Http\Controllers\Api\Quality\QmhActionItemController as ApiQmhActionItemController;
use App\Http\Controllers\Api\Quality\QmhAuditController as ApiQmhAuditController;
use App\Http\Controllers\Api\Quality\QmhDashboardController;
use App\Http\Controllers\Api\Quality\QmhDocumentController;
use App\Http\Controllers\Api\Quality\QmhGovernanceController as ApiQmhGovernanceController;
use App\Http\Controllers\Api\Quality\QmhKumController as ApiQmhKumController;
use App\Http\Controllers\Api\Quality\QmhPendukungController as ApiQmhPendukungController;
use App\Http\Controllers\Api\Quality\QmhPreviewController;
use App\Http\Controllers\Api\Quality\QmhRapatController as ApiQmhRapatController;
use App\Http\Controllers\Api\Quality\QmhReportingController;
use App\Http\Controllers\Api\Quality\QmhRevisionWorkflowController;
use App\Http\Controllers\Api\Quality\QmhTemplateController;
use App\Http\Controllers\Api\RequestDocumentsController;
use App\Http\Controllers\Api\SampleProcessController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\Settings\BladeTemplateEditorController;
use App\Http\Controllers\Api\Settings\BrandingController;
use App\Http\Controllers\Api\Settings\DocumentMaintenanceController;
use App\Http\Controllers\Api\Settings\DocumentTemplateController;
use App\Http\Controllers\Api\Settings\EmergencyBackupController;
use App\Http\Controllers\Api\Settings\IkuSettingsController;
use App\Http\Controllers\Api\Settings\LocalizationRetentionController;
use App\Http\Controllers\Api\Settings\NotificationsController;
use App\Http\Controllers\Api\Settings\NumberingController;
use App\Http\Controllers\Api\Settings\SettingsLocalizationController;
use App\Http\Controllers\Api\Settings\TemplateController as ApiTemplateController;
use App\Http\Controllers\Api\Settings\WhatsAppSettingsController;
use App\Http\Controllers\Api\SettingsController as ApiSettingsController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Models\TestRequest;
use Illuminate\Support\Facades\Route;

// WhatsApp Webhook (HMAC verified - Story 1.1)
Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('whatsapp.webhook');

// System API (called from WhatsApp bot)
Route::post('/system/restart-queue', [SystemController::class, 'restartQueue'])
    ->middleware('throttle:5,1')
    ->name('system.restart-queue');

// Dashboard Stats (called from WhatsApp bot)
Route::get('/dashboard-stats', [\App\Http\Controllers\DashboardController::class, 'getStats'])
    ->middleware('throttle:60,1')
    ->name('api.dashboard.stats');

// Monitoring API
Route::prefix('monitoring')->middleware('throttle:60,1')->group(function () {
    Route::post('/data', [\App\Http\Controllers\Api\MonitoringController::class, 'store']);
});

Route::middleware(['throttle:120,1'])->group(function () {

    Route::middleware(['auth', 'verified'])->prefix('quality')->group(function () {
        Route::get('/documents', [QmhDocumentController::class, 'index'])
            ->middleware('permission:qmh.view');

        Route::post('/documents', [QmhDocumentController::class, 'store'])
            ->middleware('permission:qmh.create');

        Route::get('/pendukung', [ApiQmhPendukungController::class, 'index'])
            ->middleware(['permission:qmh.view', 'throttle:60,1']);

        Route::post('/pendukung', [ApiQmhPendukungController::class, 'store'])
            ->middleware(['permission:qmh.create', 'throttle:10,1']);

        Route::get('/pendukung/{document}', [ApiQmhPendukungController::class, 'show'])
            ->middleware(['permission:qmh.view', 'throttle:60,1'])
            ->whereNumber('document');

        Route::put('/pendukung/{document}', [ApiQmhPendukungController::class, 'update'])
            ->middleware(['permission:qmh.create', 'throttle:10,1'])
            ->whereNumber('document');

        Route::delete('/pendukung/{document}', [ApiQmhPendukungController::class, 'destroy'])
            ->middleware(['permission:qmh.create', 'throttle:30,1'])
            ->whereNumber('document');

        Route::post('/pendukung/{document}/version', [ApiQmhPendukungController::class, 'createVersion'])
            ->middleware(['permission:qmh.create', 'throttle:10,1'])
            ->whereNumber('document');

        Route::get('/pendukung/clause/{clause}', [ApiQmhPendukungController::class, 'byClause'])
            ->middleware(['permission:qmh.view', 'throttle:60,1']);

        Route::get('/templates', [QmhTemplateController::class, 'index'])
            ->middleware('permission:qmh.create');

        Route::get('/rapat', [ApiQmhRapatController::class, 'index'])
            ->middleware(['permission:qmh.view', 'throttle:60,1']);

        Route::get('/audit', [ApiQmhAuditController::class, 'index'])
            ->middleware(['permission:qmh.view', 'throttle:60,1']);

        Route::get('/kum', [ApiQmhKumController::class, 'index'])
            ->middleware(['permission:qmh.view', 'throttle:60,1']);

        Route::post('/kum/{kum}/action-items', [ApiQmhKumController::class, 'storeActionItems'])
            ->middleware(['permission:qmh.create', 'throttle:30,1'])
            ->whereNumber('kum');

        Route::get('/governance/summary', [ApiQmhGovernanceController::class, 'summary'])
            ->middleware(['permission:qmh.view', 'throttle:60,1']);

        Route::prefix('action-items')->group(function () {
            Route::get('/', [ApiQmhActionItemController::class, 'index'])
                ->middleware(['permission:qmh.view', 'throttle:60,1']);
            Route::get('/{actionItem}', [ApiQmhActionItemController::class, 'show'])
                ->middleware(['permission:qmh.view', 'throttle:60,1'])
                ->whereNumber('actionItem');

            Route::post('/', [ApiQmhActionItemController::class, 'store'])
                ->middleware(['permission:qmh.create', 'throttle:30,1']);
            Route::put('/{actionItem}', [ApiQmhActionItemController::class, 'update'])
                ->middleware(['permission:qmh.create', 'throttle:30,1'])
                ->whereNumber('actionItem');
            Route::patch('/{actionItem}/state', [ApiQmhActionItemController::class, 'updateState'])
                ->middleware(['permission:qmh.create', 'throttle:30,1'])
                ->whereNumber('actionItem');

            Route::post('/{actionItem}/dependencies', [ApiQmhActionItemController::class, 'addDependency'])
                ->middleware(['permission:qmh.create', 'throttle:30,1'])
                ->whereNumber('actionItem');
            Route::delete('/{actionItem}/dependencies/{dependency}', [ApiQmhActionItemController::class, 'removeDependency'])
                ->middleware(['permission:qmh.create', 'throttle:30,1'])
                ->whereNumber('actionItem')
                ->whereNumber('dependency');
            Route::get('/{actionItem}/dependency-graph', [ApiQmhActionItemController::class, 'dependencyGraph'])
                ->middleware(['permission:qmh.view', 'throttle:60,1'])
                ->whereNumber('actionItem');
        });

        Route::post('/preview/pdf', [QmhPreviewController::class, 'pdf'])
            ->middleware(['permission:qmh.create', 'throttle:30,1']);

        Route::post('/preview/artifacts', [QmhPreviewController::class, 'storeArtifact'])
            ->middleware(['permission:qmh.create', 'throttle:30,1']);

        Route::get('/dashboard/stats', [QmhDashboardController::class, 'stats'])
            ->middleware('permission:qmh.view');

        Route::get('/dashboard/tips', [QmhDashboardController::class, 'tips'])
            ->middleware('permission:qmh.view');

        Route::get('/dashboard/summary', [QmhReportingController::class, 'summary'])
            ->middleware('permission:qmh.report');

        Route::prefix('reports')->middleware('permission:qmh.report')->group(function () {
            Route::get('/revision-history', [QmhReportingController::class, 'revisionHistory']);
            Route::get('/revision-history/export', [QmhReportingController::class, 'revisionHistoryExport']);
            Route::get('/download-history', [QmhReportingController::class, 'downloadHistory']);
            Route::get('/download-history/export', [QmhReportingController::class, 'downloadHistoryExport']);
            Route::get('/controlled-distribution', [QmhReportingController::class, 'controlledDistribution']);
            Route::get('/controlled-distribution/export', [QmhReportingController::class, 'controlledDistributionExport']);
        });

        Route::prefix('revisions/{revision}')->middleware('permission:qmh.create')->group(function () {
            Route::post('/lock', [QmhRevisionWorkflowController::class, 'lock']);
            Route::post('/heartbeat', [QmhRevisionWorkflowController::class, 'heartbeat']);
            Route::post('/unlock', [QmhRevisionWorkflowController::class, 'unlock']);
            Route::put('/content', [QmhRevisionWorkflowController::class, 'saveContent']);
            Route::post('/submit', [QmhRevisionWorkflowController::class, 'submit']);
            Route::post('/template-fallback/request', [QmhRevisionWorkflowController::class, 'requestTemplateFallback']);
            Route::post('/review', [QmhRevisionWorkflowController::class, 'review']);
            Route::post('/approve', [QmhRevisionWorkflowController::class, 'approve']);
            Route::post('/reject', [QmhRevisionWorkflowController::class, 'reject']);
            Route::post('/close-legacy-and-duplicate-to-v2', [QmhRevisionWorkflowController::class, 'closeLegacyAndDuplicateToV2']);
            Route::post('/download', [QmhRevisionWorkflowController::class, 'download']);
            Route::post('/preview/pdf', [QmhRevisionWorkflowController::class, 'previewPdf']); // Task 7
        });

        Route::post('/revisions/{revision}/template-fallback/review', [QmhRevisionWorkflowController::class, 'reviewTemplateFallback'])
            ->middleware('permission:qmh.template.manage');
    });

    Route::middleware(['auth', 'verified'])->prefix('settings')->group(function () {
        Route::get('/', [ApiSettingsController::class, 'index']);
        Route::put('/', [\App\Http\Controllers\SettingsController::class, 'update']);

        Route::get('/numbering/current', [NumberingController::class, 'current']);
        Route::put('/numbering/{scope}', [NumberingController::class, 'updateScope']);
        Route::put('/numbering', [NumberingController::class, 'update']);
        Route::post('/numbering/preview', [NumberingController::class, 'preview']);

        // Numbering Repair (Admin/Manager only)
        Route::prefix('numbering/repair')->group(function () {
            Route::get('/change-logs', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'changeLogs']);
            Route::get('/{scope}/status', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'counterStatus']);
            Route::get('/{scope}/scan', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'scan']);
            Route::get('/{scope}/search', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'search']);
            Route::get('/{scope}/list', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'documentList']);
            Route::get('/{scope}/document/{id}', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'getDocument']);
            Route::post('/{scope}/reset', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'reset']);
            Route::post('/{scope}/sync', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'sync']);
            Route::post('/{scope}/docs/{id}/repair', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'repair']);

            // Reclaim Gap Routes
            Route::get('/{scope}/can-reclaim', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'canReclaim']);
            Route::post('/{scope}/reclaim', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'reclaim']);

            // Compact (sample_code only for now)
            Route::get('/{scope}/compact-preview', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'compactPreview']);
            Route::post('/{scope}/compact', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'compact']);
        });

        Route::get('/templates', [ApiTemplateController::class, 'index']);
        Route::post('/templates/upload', [ApiTemplateController::class, 'upload']);
        Route::put('/templates/{template}/activate', [ApiTemplateController::class, 'activate']);
        Route::delete('/templates/{template}', [ApiTemplateController::class, 'destroy']);
        Route::get('/templates/{template}/preview', [ApiTemplateController::class, 'preview']);

        // Document Templates (New unified system)
        Route::prefix('document-templates')->name('document-templates.')->group(function () {
            Route::get('/', [DocumentTemplateController::class, 'index'])->name('index');
            Route::get('/by-type/{type}', [DocumentTemplateController::class, 'byType'])->name('by-type');
            Route::get('/{template}', [DocumentTemplateController::class, 'show'])
                ->whereNumber('template')
                ->name('show');
            Route::post('/', [DocumentTemplateController::class, 'store'])->name('store');
            Route::post('/upload', [DocumentTemplateController::class, 'upload'])->name('upload');
            Route::put('/{template}/activate', [DocumentTemplateController::class, 'activate'])->name('activate');
            Route::put('/{template}/deactivate', [DocumentTemplateController::class, 'deactivate'])->name('deactivate');
            Route::put('/{template}', [DocumentTemplateController::class, 'update'])
                ->whereNumber('template')
                ->name('update');
            Route::get('/preview/{type}/{format}', [DocumentTemplateController::class, 'preview'])->name('preview');
            Route::get('/{template}/preview/html', [DocumentTemplateController::class, 'previewTemplateHtml'])
                ->whereNumber('template')
                ->middleware('throttle:document-template-preview')
                ->name('preview-html');
            Route::get('/{template}/preview/pdf', [DocumentTemplateController::class, 'previewTemplatePdf'])
                ->whereNumber('template')
                ->middleware('throttle:document-template-preview')
                ->name('preview-pdf');
            Route::put('/{template}/content', [DocumentTemplateController::class, 'updateContent'])->name('update-content');
            Route::delete('/{template}', [DocumentTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::put('/branding', [BrandingController::class, 'update']);
        Route::post('/pdf/preview', [BrandingController::class, 'previewPdf']);

        Route::put('/localization-retention', [LocalizationRetentionController::class, 'update']);
        Route::get('/localization/time-preview', [SettingsLocalizationController::class, 'timePreview']);
        Route::put('/notifications-security', [NotificationsController::class, 'update']);
        Route::post('/notifications/test', [NotificationsController::class, 'test']);
        Route::get('/documents', [DocumentMaintenanceController::class, 'index']);
        Route::delete('/documents', [DocumentMaintenanceController::class, 'destroy']);
        Route::get('/documents/cleanup-stats', [DocumentMaintenanceController::class, 'cleanupStats']);
        Route::post('/documents/cleanup-orphaned', [DocumentMaintenanceController::class, 'cleanupOrphanedFolders']);
        Route::post('/documents/cleanup-duplicates', [DocumentMaintenanceController::class, 'cleanupDuplicates']);

        // IKU Settings
        Route::get('/iku', [IkuSettingsController::class, 'show']);
        Route::put('/iku', [IkuSettingsController::class, 'update']);
        Route::get('/iku/preview', [IkuSettingsController::class, 'preview']);

        // Blade Template Editor
        Route::prefix('blade-templates')->name('blade-templates.')->middleware(\App\Http\Middleware\ValidateBladeTemplateAccess::class)->group(function () {
            Route::get('/', [BladeTemplateEditorController::class, 'index'])->name('index');
            Route::get('/{template}', [BladeTemplateEditorController::class, 'show'])->name('show');
            Route::put('/{template}', [BladeTemplateEditorController::class, 'update'])->name('update');
            Route::post('/{template}/preview', [BladeTemplateEditorController::class, 'preview'])->name('preview');
            Route::get('/{template}/backups', [BladeTemplateEditorController::class, 'backups'])->name('backups');
            Route::post('/{template}/restore', [BladeTemplateEditorController::class, 'restore'])->name('restore');
        });

        // Emergency Backup
        Route::prefix('emergency-backup')->group(function () {
            Route::post('/', [EmergencyBackupController::class, 'start']);
            Route::get('/', [EmergencyBackupController::class, 'list']);
            Route::get('/{id}', [EmergencyBackupController::class, 'show']);
            Route::get('/{id}/download/{file}', [EmergencyBackupController::class, 'download']);
        });

        // WhatsApp Notifications
        Route::prefix('notifications/whatsapp')->group(function () {
            Route::put('/', [WhatsAppSettingsController::class, 'update']);
            Route::post('/test', [WhatsAppSettingsController::class, 'test']);
            Route::post('/check-connection', [WhatsAppSettingsController::class, 'checkConnection']);
            Route::get('/health', [WhatsAppSettingsController::class, 'checkHealth']);
            Route::get('/logs', [WhatsAppSettingsController::class, 'getOutboxLogs']);
            Route::get('/templates', [WhatsAppSettingsController::class, 'getTemplates']);
            Route::get('/devices', [WhatsAppSettingsController::class, 'getDevices']);

            // Template Management (All Categories)
            Route::get('/templates/all', [WhatsAppSettingsController::class, 'getAllTemplates']);
            Route::get('/templates/{category}', [WhatsAppSettingsController::class, 'getTemplateCategory']);
            Route::put('/templates', [WhatsAppSettingsController::class, 'updateTemplate']);
            Route::put('/templates/{category}', [WhatsAppSettingsController::class, 'updateTemplateCategory']);
            Route::post('/templates/reset', [WhatsAppSettingsController::class, 'resetTemplate']);
            Route::post('/templates/preview', [WhatsAppSettingsController::class, 'previewTemplate']);
        });

        // Survey Questions Management
        Route::get('/survey-questions', [\App\Http\Controllers\Api\Settings\SurveyQuestionsController::class, 'index']);
        Route::put('/survey-questions', [\App\Http\Controllers\Api\Settings\SurveyQuestionsController::class, 'update']);
    });

    // Job Status Polling (Global)
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/jobs/{id}', [JobStatusController::class, 'show']);
    });

    // Environment Monitoring API
    Route::middleware(['auth', 'verified'])->prefix('monitoring')->group(function () {
        Route::get('/environment/due', [\App\Http\Controllers\EnvironmentMonitoringController::class, 'apiDueList']);
        Route::get('/environment/locations', [\App\Http\Controllers\EnvironmentMonitoringController::class, 'apiLocations']);
    });

    Route::middleware([
        'auth',
        'throttle:search',
    ])->group(function () {
        Route::get('/search', SearchController::class)->name('api.search');
        Route::get('/people/{person}', PeopleController::class)->name('api.people.show');
        Route::get('/documents/{document}', DocumentController::class)->name('api.documents.show');
        Route::get('/documents/{document}/download', DocumentDownloadController::class)
            ->name('api.documents.download')
            ->middleware('audit.activity:DOCUMENT_DOWNLOADED,document');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/requests/{testRequest}/documents', [RequestDocumentsController::class, 'index']);
        Route::delete('/requests/{testRequest}/documents/{type}', [RequestDocumentsController::class, 'destroy']);
        Route::delete('/documents/{document}', DocumentDeleteController::class);
    });

    // Sample Process Quick Actions API
    Route::middleware(['auth', 'verified'])->prefix('processes')->group(function () {
        Route::get('{process}', [SampleProcessController::class, 'show']);
        Route::post('{process}/start', [SampleProcessController::class, 'start']);
        Route::post('{process}/complete', [SampleProcessController::class, 'complete']);
        Route::put('{process}/notes', [SampleProcessController::class, 'updateNotes']);
    });

    // Label Management API
    Route::middleware(['auth', 'verified'])->prefix('labels')->group(function () {
        Route::post('evidence-units', [\App\Http\Controllers\Api\LabelController::class, 'createEvidenceUnits']);
        Route::post('remaining-units', [\App\Http\Controllers\Api\LabelController::class, 'createRemainingUnit']);
        Route::get('evidence-units/{requestId}', [\App\Http\Controllers\Api\LabelController::class, 'getEvidenceUnits']);
        Route::get('remaining-units/{evidenceUnitId}', [\App\Http\Controllers\Api\LabelController::class, 'getRemainingUnits']);
        Route::get('available-samples/{requestId}', [\App\Http\Controllers\Api\LabelController::class, 'getAvailableSamples']);
    });

    // API endpoint untuk generator Berita Acara
    Route::get('/requests/{requestNumber}', function ($requestNumber) {
        $request = TestRequest::with(['investigator', 'samples', 'user'])
            ->where('request_number', $requestNumber)
            ->first();

        if (! $request) {
            return response()->json(['error' => 'Request not found'], 404);
        }

        // Format test methods untuk display
        $formatTestMethods = function ($methods) {
            if (is_string($methods)) {
                $methods = json_decode($methods, true) ?? [];
            }
            $map = [
                'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',
                'gc_ms' => 'Identifikasi GC-MS',
                'lc_ms' => 'Identifikasi LC-MS',
            ];

            return collect($methods)->map(fn ($m) => $map[$m] ?? $m)->join('; ');
        };

        return response()->json([
            'request_id' => $request->id,
            'request_no' => $request->request_number,
            'surat_permintaan_no' => $request->case_number ?? '',
            'received_date' => $request->received_at ? $request->received_at->format('d F Y') : now()->format('d F Y'),
            'customer_rank_name' => $request->investigator->rank.' '.$request->investigator->name,
            'customer_no' => $request->investigator->nrp ?? '',
            'unit' => $request->investigator->jurisdiction ?? '',
            'addressed_to' => $request->to_office ?? 'Kepala Sub Satker Farmapol Pusdokkes Polri',
            'tests_summary' => $request->samples->map(fn ($s) => $formatTestMethods($s->test_methods))->unique()->join('; '),
            'sample_count' => $request->samples->count(),
            'samples' => $request->samples->map(function ($sample) use ($formatTestMethods) {
                return [
                    'short_description' => $sample->short_description,
                    'name' => $sample->short_description,
                    'tests' => $formatTestMethods($sample->test_methods),
                    'active' => $sample->active_substance ?? '',
                ];
            }),
            'submitted_by' => $request->investigator->rank.' '.$request->investigator->name,
            'received_by' => 'Petugas Administrasi (dokumen) & Petugas Laboratorium (sampel)',
            'source_printed_at' => $request->submitted_at ? $request->submitted_at->format('d F Y H:i:s') : '',
        ]);
    });

    // API endpoint untuk generator Laporan Hasil Uji
    Route::get('/sample-processes/{processId}', function ($processId) {
        // Refresh dari database untuk memastikan data terbaru
        $process = \App\Models\SampleTestProcess::with(['sample.testRequest.investigator', 'analyst'])
            ->findOrFail($processId);

        // Force reload dari database
        $process->refresh();
        $process->load(['sample.testRequest.investigator', 'analyst']);

        $sample = $process->sample;
        $testRequest = $sample?->testRequest;
        $investigator = $testRequest?->investigator;
        $metadata = $process->metadata ?? [];

        // Format test methods
        $formatTestMethods = function ($methods) {
            if (is_string($methods)) {
                $methods = json_decode($methods, true) ?? [];
            }
            $map = [
                'uv_vis' => 'Identifikasi UV-VIS',
                'gc_ms' => 'Identifikasi GC-MS',
                'lc_ms' => 'Identifikasi LC-MS',
            ];

            return collect($methods)->map(fn ($m) => $map[$m] ?? $m)->join(', ');
        };

        // Get test result
        $resultRaw = $metadata['test_result'] ?? null;
        $resultLabel = match ($resultRaw) {
            'positive' => 'Positif',
            'negative' => 'Negatif',
            default => 'Belum ditentukan',
        };

        $testResultPrefix = match ($resultRaw) {
            'positive' => '(+)',
            'negative' => '(-)',
            default => '',
        };

        $detected = $metadata['detected_substance'] ?? $metadata['detection'] ?? $metadata['hasil'] ?? ($sample?->active_substance ?: 'Tidak ada hasil terdeteksi');
        $testResultText = trim($testResultPrefix.' '.$detected);

        // Format quantity
        $quantityDisplay = '-';
        if ($sample?->quantity) {
            $quantityDisplay = rtrim(rtrim(number_format($sample->quantity, 2, ',', '.'), '0'), ',').' '.($sample->quantity_unit ?? '');
        }

        return response()->json([
            'process_id' => $process->id,
            'report_number' => $metadata['report_number'] ?? sprintf('FLHU%03d', $process->id),
            'customer_unit' => $investigator?->jurisdiction ?? $investigator?->name ?? '-',
            'customer_name' => trim(($investigator?->rank ? $investigator->rank.' ' : '').($investigator?->name ?? '')),
            'customer_address' => $testRequest?->delivery_address ?? '-',
            'request_number' => $testRequest?->request_number ?? '-',
            'case_number' => $testRequest?->case_number ?? '-',
            'short_description' => $sample?->short_description ?? '-',
            'sample_code' => $sample?->sample_code ?? '-',
            'quantity_display' => $quantityDisplay,
            'batch_number' => $sample?->batch_number ?? '-',
            'expiry_date' => $sample?->expiry_date ? $sample->expiry_date->format('d F Y') : '-',
            // Tanggal penerimaan = tanggal formulir pengujian diisi (submitted_at)
            'received_date' => $testRequest?->submitted_at ? $testRequest->submitted_at->format('d F Y') : ($testRequest?->received_at ? $testRequest->received_at->format('d F Y') : '-'),
            'test_date' => $sample?->test_date ? $sample->test_date->format('d F Y') : '-',
            'tests_summary' => $formatTestMethods($sample?->test_methods ?? []),
            'test_result_text' => $testResultText,
            'test_result_label' => $resultLabel,
            'test_result_raw' => $resultRaw,
            'detected_substance' => $detected,
            'instrument_label' => $metadata['instrument'] ?? $metadata['instrument_pengujian'] ?? $sample?->test_type ?? '-',
            'report_date' => now()->format('d F Y'),
        ]);
    });

});
