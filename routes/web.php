<?php

use App\Http\Controllers\AnalystController;
use App\Http\Controllers\ConsolidatedReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\EnvironmentMonitoringController;
use App\Http\Controllers\InstrumentLoggingController;
use App\Http\Controllers\InvestigatorManagementController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Quality\QmhAuditController as QualityQmhAuditController;
use App\Http\Controllers\Quality\QmhDocumentController as QualityQmhDocumentController;
use App\Http\Controllers\Quality\QmhGovernanceController as QualityQmhGovernanceController;
use App\Http\Controllers\Quality\QmhKumController as QualityQmhKumController;
use App\Http\Controllers\Quality\QmhPendukungController as QualityQmhPendukungController;
use App\Http\Controllers\Quality\QmhRapatController as QualityQmhRapatController;
use App\Http\Controllers\Quality\QmhReportController as QualityQmhReportController;
use App\Http\Controllers\Quality\QmhTemplateController as QualityQmhTemplateController;
use App\Http\Controllers\Reports\MonthlyLogReportController;
use App\Http\Controllers\Reports\SurveyExportController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SampleTestController;
use App\Http\Controllers\SampleTestProcessController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\NumberingController;
use App\Http\Controllers\Settings\TemplateController as SettingsTemplateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsPageController;
use App\Http\Controllers\StaffTaskController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Locale switch
Route::post('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Public Routes
Route::get('/health', [\App\Http\Controllers\HealthController::class, 'index'])->name('health');
Route::get('/health/liveness', [\App\Http\Controllers\HealthController::class, 'liveness'])->name('health.liveness');
Route::get('/health/readiness', [\App\Http\Controllers\HealthController::class, 'readiness'])->name('health.readiness');
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return app(LandingPageController::class)();
});

// Public Tracking
Route::get('/track', [TrackingController::class, 'index'])->name('public.tracking');
Route::post('/track', [TrackingController::class, 'store'])->name('public.track');
Route::get('/track/{tracking_number}.json', [TrackingController::class, 'json'])->name('public.tracking.json');

// Authenticated Routes
Route::middleware(['auth', 'verified'])->group(function () {

    Route::prefix('quality')->name('quality.')->group(function () {
        Route::get('/', [QualityQmhDocumentController::class, 'landing'])
            ->name('index')
            ->middleware('permission:qmh.view');

        Route::get('/documents', [QualityQmhDocumentController::class, 'index'])
            ->name('documents.index')
            ->middleware('permission:qmh.view');

        Route::get('/governance', [QualityQmhGovernanceController::class, 'index'])
            ->name('governance.index')
            ->middleware('any_permission:qmh.view|qmh.rapat.view|qmh.rapat.view.all|qmh.audit.view|qmh.audit.view.all|qmh.kum.view|qmh.kum.view.all');

        Route::get('/documents/create', [QualityQmhDocumentController::class, 'create'])
            ->name('documents.create')
            ->middleware('permission:qmh.create');

        Route::post('/documents', [QualityQmhDocumentController::class, 'store'])
            ->name('documents.store')
            ->middleware('permission:qmh.create');

        Route::get('/documents/{document}', [QualityQmhDocumentController::class, 'show'])
            ->name('documents.show')
            ->middleware('permission:qmh.view');

        Route::get('/documents/{document}/edit', [QualityQmhDocumentController::class, 'edit'])
            ->name('documents.edit')
            ->middleware('permission:qmh.create');

        Route::delete('/documents/{document}', [QualityQmhDocumentController::class, 'destroy'])
            ->name('documents.destroy')
            ->middleware('permission:qmh.create');

        Route::middleware('permission:qmh.view')->group(function () {
            Route::get('/pendukung', [QualityQmhPendukungController::class, 'index'])
                ->name('pendukung.index');
            Route::get('/pendukung/create', [QualityQmhPendukungController::class, 'create'])
                ->name('pendukung.create')
                ->middleware('permission:qmh.create');
            Route::get('/pendukung/{document}', [QualityQmhPendukungController::class, 'show'])
                ->name('pendukung.show')
                ->whereNumber('document');
            Route::get('/pendukung/{document}/file', [QualityQmhPendukungController::class, 'file'])
                ->name('pendukung.file')
                ->middleware('throttle:downloads')
                ->whereNumber('document');
        });

        Route::middleware('permission:qmh.create')->group(function () {
            Route::post('/pendukung', [QualityQmhPendukungController::class, 'store'])
                ->name('pendukung.store');
            Route::get('/pendukung/{document}/edit', [QualityQmhPendukungController::class, 'edit'])
                ->name('pendukung.edit')
                ->whereNumber('document');
            Route::patch('/pendukung/{document}', [QualityQmhPendukungController::class, 'update'])
                ->name('pendukung.update')
                ->whereNumber('document');
            Route::delete('/pendukung/{document}', [QualityQmhPendukungController::class, 'destroy'])
                ->name('pendukung.destroy')
                ->whereNumber('document');
        });

        Route::get('/reports', [QualityQmhReportController::class, 'index'])
            ->name('reports.index')
            ->middleware('permission:qmh.report');

        Route::get('/templates/{template}/preview', [QualityQmhTemplateController::class, 'preview'])
            ->name('templates.preview');

        Route::get('/rapat', [QualityQmhRapatController::class, 'index'])
            ->name('rapat.index')
            ->middleware('any_permission:qmh.rapat.view|qmh.rapat.view.all|qmh.view');
        Route::get('/rapat/whatsapp/groups', [QualityQmhRapatController::class, 'whatsappGroups'])
            ->name('rapat.whatsapp.groups');
        Route::get('/rapat/create', [QualityQmhRapatController::class, 'create'])
            ->name('rapat.create')
            ->middleware('permission:qmh.create');
        Route::post('/rapat', [QualityQmhRapatController::class, 'store'])
            ->name('rapat.store')
            ->middleware('permission:qmh.create');
        Route::get('/rapat/{rapat}/pdf', [QualityQmhRapatController::class, 'pdf'])
            ->name('rapat.pdf')
            ->middleware('any_permission:qmh.rapat.view|qmh.rapat.view.all|qmh.view')
            ->whereNumber('rapat');
        Route::post('/rapat/{rapat}/whatsapp/send', [QualityQmhRapatController::class, 'sendWhatsAppSummary'])
            ->name('rapat.whatsapp.send')
            ->whereNumber('rapat');
        Route::post('/rapat/{rapat}/attachments', [QualityQmhRapatController::class, 'storeAttachments'])
            ->name('rapat.attachments.store')
            ->whereNumber('rapat');
        Route::get('/rapat/{rapat}/attachments/{attachment}/file', [QualityQmhRapatController::class, 'fileAttachment'])
            ->name('rapat.attachments.file')
            ->whereNumber('rapat')
            ->whereNumber('attachment');
        Route::delete('/rapat/{rapat}/attachments/{attachment}', [QualityQmhRapatController::class, 'destroyAttachment'])
            ->name('rapat.attachments.destroy')
            ->whereNumber('rapat')
            ->whereNumber('attachment');
        Route::get('/rapat/{rapat}', [QualityQmhRapatController::class, 'show'])
            ->name('rapat.show')
            ->whereNumber('rapat');
        Route::patch('/rapat/{rapat}', [QualityQmhRapatController::class, 'update'])
            ->name('rapat.update')
            ->middleware('permission:qmh.create')
            ->whereNumber('rapat');
        Route::delete('/rapat/{rapat}', [QualityQmhRapatController::class, 'destroy'])
            ->name('rapat.destroy')
            ->middleware('permission:qmh.create')
            ->whereNumber('rapat');
        Route::post('/rapat/{rapat}/peserta', [QualityQmhRapatController::class, 'storePeserta'])
            ->name('rapat.peserta.store')
            ->middleware('permission:qmh.create')
            ->whereNumber('rapat');
        Route::post('/rapat/{rapat}/notulensi', [QualityQmhRapatController::class, 'storeNotulensi'])
            ->name('rapat.notulensi.store')
            ->middleware('permission:qmh.create')
            ->whereNumber('rapat');
        Route::post('/rapat/{rapat}/action-items', [QualityQmhRapatController::class, 'storeActionItem'])
            ->name('rapat.action-items.store')
            ->middleware('permission:qmh.create')
            ->whereNumber('rapat');
        Route::patch('/rapat/{rapat}/action-items/{actionItem}/status', [QualityQmhRapatController::class, 'updateActionItemStatus'])
            ->name('rapat.action-items.status')
            ->middleware('action-item.transition')
            ->whereNumber('rapat')
            ->whereNumber('actionItem');

        Route::get('/audit', [QualityQmhAuditController::class, 'index'])
            ->name('audit.index')
            ->middleware('any_permission:qmh.audit.view|qmh.audit.view.all|qmh.view');
        Route::get('/audit/create', [QualityQmhAuditController::class, 'create'])
            ->name('audit.create')
            ->middleware('permission:qmh.create');
        Route::post('/audit', [QualityQmhAuditController::class, 'store'])
            ->name('audit.store')
            ->middleware('permission:qmh.create');
        Route::get('/audit/{audit}', [QualityQmhAuditController::class, 'show'])
            ->name('audit.show')
            ->whereNumber('audit');
        Route::patch('/audit/{audit}', [QualityQmhAuditController::class, 'update'])
            ->name('audit.update')
            ->middleware('permission:qmh.create')
            ->whereNumber('audit');
        Route::delete('/audit/{audit}', [QualityQmhAuditController::class, 'destroy'])
            ->name('audit.destroy')
            ->middleware('permission:qmh.create')
            ->whereNumber('audit');
        Route::post('/audit/{audit}/temuan', [QualityQmhAuditController::class, 'storeTemuan'])
            ->name('audit.temuan.store')
            ->middleware('permission:qmh.create')
            ->whereNumber('audit');
        Route::patch('/audit/{audit}/temuan/{temuan}', [QualityQmhAuditController::class, 'updateTemuan'])
            ->name('audit.temuan.update')
            ->middleware('permission:qmh.create')
            ->whereNumber('audit')
            ->whereNumber('temuan');

        Route::get('/kum', [QualityQmhKumController::class, 'index'])
            ->name('kum.index')
            ->middleware('any_permission:qmh.kum.view|qmh.kum.view.all|qmh.view');
        Route::get('/kum/create', [QualityQmhKumController::class, 'create'])
            ->name('kum.create')
            ->middleware('permission:qmh.create');
        Route::post('/kum', [QualityQmhKumController::class, 'store'])
            ->name('kum.store')
            ->middleware('permission:qmh.create');
        Route::get('/kum/{kum}', [QualityQmhKumController::class, 'show'])
            ->name('kum.show')
            ->whereNumber('kum');
        Route::patch('/kum/{kum}', [QualityQmhKumController::class, 'update'])
            ->name('kum.update')
            ->middleware('permission:qmh.create')
            ->whereNumber('kum');
        Route::delete('/kum/{kum}', [QualityQmhKumController::class, 'destroy'])
            ->name('kum.destroy')
            ->middleware('permission:qmh.create')
            ->whereNumber('kum');
        Route::post('/kum/{kum}/action-items', [QualityQmhKumController::class, 'storeActionItems'])
            ->name('kum.action-items.store')
            ->middleware('permission:qmh.create')
            ->whereNumber('kum');
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Search
    Route::view('/search', 'search.index')->name('search.index');
    Route::get('/search/data', [SearchController::class, 'data'])->name('search.data');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Lidik Sidik Index

    // Requests
    Route::resource('requests', RequestController::class);
    Route::patch('requests/{testRequest}/verified-at', [RequestController::class, 'updateVerifiedAt'])
        ->name('requests.update-verified-at');

    // Request document endpoints (sample_receipt, handover_report, request_letter_receipt)
    Route::get('/requests/{testRequest}/documents/{type}', [RequestController::class, 'downloadDocument'])
        ->name('requests.documents.download')
        ->middleware('audit.activity:DOCUMENT_DOWNLOADED');
    Route::delete('/requests/{testRequest}/documents/{type}', [RequestController::class, 'deleteDocument'])->name('requests.documents.delete');

    // Berita Acara Penerimaan
    Route::get('/requests/{testRequest}/berita-acara/check', [RequestController::class, 'checkBeritaAcara'])
        ->name('requests.berita-acara.check');
    Route::post('/requests/{testRequest}/berita-acara/generate', [RequestController::class, 'generateBeritaAcara'])
        ->name('requests.berita-acara.generate');
    Route::get('/requests/{testRequest}/berita-acara/download', [RequestController::class, 'downloadBeritaAcara'])
        ->name('requests.berita-acara.download');
    Route::get('/requests/{testRequest}/berita-acara/view', [RequestController::class, 'viewBeritaAcara'])
        ->name('requests.berita-acara.view');

    // Kaji Ulang Permintaan (Review)
    Route::prefix('kaji-ulang-permintaan')->name('review.')->group(function () {
        Route::get('/', [SampleTestController::class, 'create'])->name('create');
        Route::post('/', [SampleTestController::class, 'store'])->name('store');
        Route::post('{testRequest}/reject', [SampleTestController::class, 'reject'])->name('reject');
        Route::get('{sampleDetail}', [SampleTestController::class, 'show'])->name('show');
    });

    // Legacy Pengujian routes (redirect to review)
    Route::prefix('samples')->group(function () {
        Route::get('/test', function () {
            return redirect()->route('review.create', request()->query());
        });
        Route::post('/test', [SampleTestController::class, 'store']);
        Route::get('/test/{sampleDetail}', function ($sampleDetail) {
            return redirect()->route('review.show', ['sampleDetail' => $sampleDetail] + request()->query());
        });
        Route::get('/', function () {
            return redirect()->route('review.create', request()->query());
        });
    });

    // Pengujian (Process)
    Route::prefix('pengujian')->name('testing.')->group(function () {
        Route::get('/', [ProcessController::class, 'index'])->name('index');

        Route::prefix('processes')->name('processes.')->group(function () {
            Route::get('{sample_process}/edit', [SampleTestProcessController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '{sample_process}', [SampleTestProcessController::class, 'update'])->name('update');
            Route::delete('{sample_process}', [SampleTestProcessController::class, 'destroy'])->name('destroy');
            Route::get('{sample_process}/form/{stage}', [SampleTestProcessController::class, 'generateForm'])
                ->where('stage', '^(preparation|instrumentation)$')
                ->name('generate-form');
            Route::get('{sample_process}/lab-report', [SampleTestProcessController::class, 'generateReport'])
                ->name('lab-report');
            Route::get('{sample_process}', [SampleTestProcessController::class, 'show'])->name('show');
        });

        Route::post('{testRequest}/recent', [ProcessController::class, 'storeRecent'])->name('recent');
        Route::post('{testRequest}/ready-for-delivery', [ProcessController::class, 'markReadyForDelivery'])->name('ready-for-delivery');
        Route::get('{testRequest}', [ProcessController::class, 'show'])->name('show');
    });

    // Legacy Proses routes (redirect to pengujian)
    Route::prefix('proses')->group(function () {
        Route::get('/', function () {
            return redirect()->route('testing.index', request()->query());
        });
        Route::get('{testRequest}', function ($testRequest) {
            return redirect()->route('testing.show', ['testRequest' => $testRequest] + request()->query());
        });
    });

    Route::post('samples/{sample}/ready-for-delivery', [SampleTestProcessController::class, 'markAsReadyForDelivery'])
        ->name('samples.ready-for-delivery');
    Route::get('analysts/{analyst}/logs', [AnalystController::class, 'logs'])->name('analysts.logs');
    Route::put('analysts/{analyst}/role', [AnalystController::class, 'updateRole'])->name('analysts.role.update');
    Route::put('analysts/{analyst}/permissions', [AnalystController::class, 'updatePermissions'])->name('analysts.permissions.update');
    Route::post('analysts/{analyst}/permissions/reset', [AnalystController::class, 'resetPermissions'])->name('analysts.permissions.reset');
    Route::post('analysts/{analyst}/disable', [AnalystController::class, 'disable'])->name('analysts.disable');
    Route::post('analysts/{analyst}/enable', [AnalystController::class, 'enable'])->name('analysts.enable');
    Route::post('analysts/roles', [AnalystController::class, 'storeRole'])->name('analysts.roles.store');
    Route::patch('analysts/roles/{role}', [AnalystController::class, 'updateRoleType'])->name('analysts.roles.update');
    Route::delete('analysts/roles/{role}', [AnalystController::class, 'destroyRoleType'])->name('analysts.roles.destroy');

    // Unified Personnel Management
    Route::get('/personel', [App\Http\Controllers\PersonnelController::class, 'index'])->name('personnel.index');

    // Redirect legacy routes to unified page
    Route::get('analysts', function () {
        return redirect()->route('personnel.index', ['tab' => 'staff']);
    })->name('analysts.index');

    Route::resource('analysts', AnalystController::class)->except(['index']);

    // Investigator Management
    Route::get('investigators', function () {
        return redirect()->route('personnel.index', ['tab' => 'penyidik']);
    })->name('investigators.index');

    // Keep API/Detail routes
    Route::get('investigators/{investigator}', [InvestigatorManagementController::class, 'show'])
        ->name('investigators.show')
        ->can('investigators.view');
    Route::get('investigators/{investigator}/edit', [InvestigatorManagementController::class, 'edit'])
        ->name('investigators.edit')
        ->can('investigators.edit');
    Route::put('investigators/{investigator}', [InvestigatorManagementController::class, 'update'])
        ->name('investigators.update')
        ->can('investigators.edit');
    Route::delete('investigators/{investigator}', [InvestigatorManagementController::class, 'destroy'])
        ->name('investigators.destroy')
        ->can('investigators.delete');

    // Delivery
    Route::prefix('delivery')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('delivery.index');
        Route::get('/{request}', [DeliveryController::class, 'show'])->name('delivery.show');
        Route::patch('/{delivery}/surat-pengantar', [DeliveryController::class, 'updateSuratPengantar'])
            ->name('delivery.update-surat-pengantar');
        Route::post('/{request}/send-notification', [DeliveryController::class, 'sendPickupNotification'])
            ->name('delivery.send-notification');
        Route::post('/{request}/complete', [DeliveryController::class, 'markAsCompleted'])
            ->name('delivery.complete');

        // Handover routes
        Route::post('{delivery}/handover/generate', [DeliveryController::class, 'handoverGenerate'])
            ->name('delivery.handover.generate');
        Route::get('{delivery}/handover/view', [DeliveryController::class, 'handoverView'])
            ->name('delivery.handover.view');
        Route::get('{delivery}/handover/download', [DeliveryController::class, 'handoverDownload'])
            ->name('delivery.handover.download');
        Route::get('{request}/handover/status', [DeliveryController::class, 'handoverStatus'])
            ->name('delivery.handover.status');

        Route::get('/{request}/survey', [DeliveryController::class, 'surveyForm'])->name('delivery.survey');
        Route::post('/{request}/survey', [DeliveryController::class, 'submitSurvey'])
            ->name('delivery.survey.submit');
    });

    // Tracking
    Route::get('/tracking', [TrackingController::class, 'index'])->name('tracking.index');
    Route::post('/tracking', [TrackingController::class, 'store'])->name('tracking.store');

    // Changelogs
    Route::get('/changelogs', [\App\Http\Controllers\ChangelogController::class, 'index'])->name('changelogs.index');

    // Statistics
    Route::prefix('statistics')->group(function () {
        Route::get('/', [StatisticsController::class, 'index'])->name('statistics.index');
        Route::get('/data', [StatisticsController::class, 'data'])->name('statistics.data');
        Route::get('/export', [StatisticsController::class, 'export'])->name('statistics.export');

        // Consolidated Reports
        Route::prefix('reports')->name('consolidated-reports.')->group(function () {
            Route::get('/', [ConsolidatedReportController::class, 'index'])->name('index');
            Route::get('/history', [ConsolidatedReportController::class, 'history'])->name('history');
            Route::post('/preview', [ConsolidatedReportController::class, 'preview'])->name('preview');
            Route::post('/', [ConsolidatedReportController::class, 'store'])->name('store');
            Route::put('/default-signers', [ConsolidatedReportController::class, 'saveDefaultSigners'])->name('save-default-signers');
            Route::get('/{report}/download', [ConsolidatedReportController::class, 'download'])->name('download');
            Route::delete('/{report}', [ConsolidatedReportController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware('can:manage-settings')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/surveys/export', SurveyExportController::class)->name('surveys.export');
        Route::get('/monthly-logs', [MonthlyLogReportController::class, 'index'])->name('monthly-logs');
        Route::get('/monthly-logs/environment', [MonthlyLogReportController::class, 'environmentReport'])->name('monthly-logs.environment');
        Route::get('/monthly-logs/environment/csv', [MonthlyLogReportController::class, 'exportEnvironmentCsv'])->name('monthly-logs.environment.csv');
        Route::get('/monthly-logs/instrument', [MonthlyLogReportController::class, 'instrumentReport'])->name('monthly-logs.instrument');
        Route::get('/monthly-logs/instrument/csv', [MonthlyLogReportController::class, 'exportInstrumentCsv'])->name('monthly-logs.instrument.csv');
        Route::get('/monthly-logs/weighing', [MonthlyLogReportController::class, 'weighingReport'])->name('monthly-logs.weighing');
    });

    // Environment Monitoring
    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        // Automatic Sensors Dashboard (Redirect to Environment Input for now)
        Route::get('/sensors', fn () => redirect()->route('monitoring.environment.index'))->name('sensors.index');

        Route::prefix('environment')->name('environment.')->group(function () {
            Route::get('/', [EnvironmentMonitoringController::class, 'index'])->name('index');
            Route::post('/readings', [EnvironmentMonitoringController::class, 'storeReading'])->name('readings.store');
            Route::get('/readings/{reading}/correction', [EnvironmentMonitoringController::class, 'showCorrectionForm'])->name('readings.correction');
            Route::post('/readings/{reading}/correction', [EnvironmentMonitoringController::class, 'storeCorrection'])->name('readings.correction.store');
            Route::get('/manage', [EnvironmentMonitoringController::class, 'manage'])->name('manage');
            Route::get('/locations', [EnvironmentMonitoringController::class, 'apiLocationsList'])->name('locations.index');
            Route::post('/locations', [EnvironmentMonitoringController::class, 'storeLocation'])->name('locations.store');
            Route::put('/locations/{location}', [EnvironmentMonitoringController::class, 'updateLocation'])->name('locations.update');
            Route::delete('/locations/{location}', [EnvironmentMonitoringController::class, 'destroyLocation'])->name('locations.destroy');
        });
        Route::get('/instruments', function () {
            return view('monitoring.instruments.index');
        })->name('instruments.index');
    });

    // Instrument Logging API routes for sample workflow
    Route::prefix('api/samples/{sample}')->name('api.samples.')->group(function () {
        Route::get('/instrument-requirements', [InstrumentLoggingController::class, 'getRequirements'])->name('instrument-requirements');
        Route::post('/instrument-usage', [InstrumentLoggingController::class, 'storeUsage'])->name('instrument-usage');
        Route::get('/uvvis-weighing', [InstrumentLoggingController::class, 'checkUvvisWeighing'])->name('uvvis-weighing.check');
        Route::post('/uvvis-weighing', [InstrumentLoggingController::class, 'storeUvvisWeighing'])->name('uvvis-weighing.store');
        Route::get('/weighing', [InstrumentLoggingController::class, 'checkWeighing'])->name('weighing.check');
        Route::post('/weighing', [InstrumentLoggingController::class, 'storeWeighing'])->name('weighing.store');
    });

    Route::middleware('can:manage-settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsPageController::class, 'index'])->name('index');
        Route::post('/save', [SettingsController::class, 'update'])
            ->name('update')
            ->middleware('audit.activity:SETTINGS_UPDATED');
        Route::post('/preview', [SettingsController::class, 'preview'])->name('preview');
        Route::post('/brand-asset', [SettingsController::class, 'uploadBrandAsset'])->name('brand.upload');
        Route::post('/instrument-requirements', [SettingsController::class, 'saveInstrumentRequirements'])->name('instrument-requirements.save');

        Route::get('/templates', [SettingsTemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [SettingsTemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/activate', [SettingsTemplateController::class, 'activate'])->name('templates.activate');

        // Blade Template Editor
        Route::get('/blade-templates', function () {
            return view('settings.blade-templates');
        })->name('blade-templates');

        // Document Templates (New unified system) - Redirected to /settings
        Route::get('/document-templates', function () {
            return redirect()->route('settings.blade-templates');
        })->name('document-templates');
    });

    Route::prefix('numbering')->name('numbering.')->group(function () {
        Route::post('/{scope}/preview', [NumberingController::class, 'preview'])->name('preview');
        Route::post('/{scope}/issue', [NumberingController::class, 'issue'])->name('issue');
    });

    // Investigator Documents
    Route::prefix('investigators/{investigator}')->group(function () {
        Route::get('/documents', [App\Http\Controllers\InvestigatorDocumentController::class, 'index'])
            ->name('investigator.documents.index');
        Route::get('/documents/create', [App\Http\Controllers\InvestigatorDocumentController::class, 'create'])
            ->name('investigator.documents.create');
        Route::post('/documents', [App\Http\Controllers\InvestigatorDocumentController::class, 'store'])
            ->name('investigator.documents.store');
    });

    Route::get('/documents/{document}', [App\Http\Controllers\InvestigatorDocumentController::class, 'show'])
        ->name('investigator.documents.show');
    Route::get('/documents/{document}/download', [App\Http\Controllers\InvestigatorDocumentController::class, 'download'])
        ->name('investigator.documents.download')
        ->middleware(['signed', 'audit.activity:DOCUMENT_DOWNLOADED,document']);
    Route::delete('/documents/{document}', [App\Http\Controllers\InvestigatorDocumentController::class, 'destroy'])
        ->name('investigator.documents.destroy');

    // WhatsApp Hub (Admin only)
    Route::prefix('whatsapp')->name('whatsapp.')->middleware('can:manage-settings')->group(function () {
        // Main page
        Route::get('/', [\App\Http\Controllers\WhatsAppHubController::class, 'index'])->name('index');

        // API endpoints
        Route::get('/overview', [\App\Http\Controllers\WhatsAppHubController::class, 'getOverviewData'])->name('overview');
        Route::get('/connection', [\App\Http\Controllers\WhatsAppHubController::class, 'getConnectionStatus'])->name('connection');

        // Tasks
        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::get('/', [\App\Http\Controllers\WhatsAppHubController::class, 'getTasks'])->name('index');
            Route::post('/', [\App\Http\Controllers\WhatsAppHubController::class, 'storeTask'])->name('store');
            Route::put('/{task}', [\App\Http\Controllers\WhatsAppHubController::class, 'updateTask'])->name('update');
            Route::patch('/{task}/status', [\App\Http\Controllers\WhatsAppHubController::class, 'updateTaskStatus'])->name('status');
            Route::delete('/{task}', [\App\Http\Controllers\WhatsAppHubController::class, 'destroyTask'])->name('destroy');
        });

        // Broadcasts
        Route::prefix('broadcasts')->name('broadcasts.')->group(function () {
            Route::get('/', [\App\Http\Controllers\WhatsAppHubController::class, 'getBroadcasts'])->name('index');
            Route::post('/', [\App\Http\Controllers\WhatsAppHubController::class, 'storeBroadcast'])->name('store');
            Route::post('/preview-recipients', [\App\Http\Controllers\WhatsAppHubController::class, 'previewRecipients'])->name('preview-recipients');
            Route::put('/{broadcast}', [\App\Http\Controllers\WhatsAppHubController::class, 'updateBroadcast'])->name('update');
            Route::delete('/{broadcast}', [\App\Http\Controllers\WhatsAppHubController::class, 'deleteBroadcast'])->name('destroy');
            Route::post('/{broadcast}/send', [\App\Http\Controllers\WhatsAppHubController::class, 'sendBroadcast'])->name('send');
            Route::post('/{broadcast}/cancel', [\App\Http\Controllers\WhatsAppHubController::class, 'cancelBroadcast'])->name('cancel');
        });

        // Reminders
        Route::prefix('reminders')->name('reminders.')->group(function () {
            Route::get('/', [\App\Http\Controllers\WhatsAppHubController::class, 'getReminders'])->name('index');
            Route::post('/', [\App\Http\Controllers\WhatsAppHubController::class, 'storeReminder'])->name('store');
            Route::put('/{reminder}', [\App\Http\Controllers\WhatsAppHubController::class, 'updateReminder'])->name('update');
            Route::delete('/{reminder}', [\App\Http\Controllers\WhatsAppHubController::class, 'deleteReminder'])->name('destroy');
            Route::post('/{reminder}/toggle', [\App\Http\Controllers\WhatsAppHubController::class, 'toggleReminder'])->name('toggle');
            Route::post('/{reminder}/trigger', [\App\Http\Controllers\WhatsAppHubController::class, 'triggerReminder'])->name('trigger');
        });

        // Logs
        Route::get('/logs', [\App\Http\Controllers\WhatsAppHubController::class, 'getLogs'])->name('logs');
        Route::get('/logs/{batch}', [\App\Http\Controllers\WhatsAppHubController::class, 'getLogDetail'])->name('logs.detail');

        // Inventory Alerts
        Route::get('/inventory-alerts', [\App\Http\Controllers\WhatsAppHubController::class, 'getInventoryAlerts'])->name('inventory-alerts');

        // Groups
        Route::get('/groups', [\App\Http\Controllers\WhatsAppHubController::class, 'getGroups'])->name('groups');
        Route::post('/groups/fetch', [\App\Http\Controllers\WhatsAppHubController::class, 'getGroups'])->name('groups.fetch'); // Alias for fetch modal
        Route::get('/groups/{jid}/participants', [\App\Http\Controllers\WhatsAppHubController::class, 'getGroupParticipants'])->name('groups.participants');

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\WhatsAppHubController::class, 'getSettings'])->name('index');
            Route::post('/', [\App\Http\Controllers\WhatsAppHubController::class, 'saveSettings'])->name('save');
            Route::get('/devices', [\App\Http\Controllers\WhatsAppHubController::class, 'getDevices'])->name('devices');
            Route::post('/test-message', [\App\Http\Controllers\WhatsAppHubController::class, 'sendTestMessage'])->name('test-message');
            Route::post('/test-ai', [\App\Http\Controllers\WhatsAppHubController::class, 'sendTestAi'])->name('test-ai');
            Route::post('/check-connection', [\App\Http\Controllers\WhatsAppHubController::class, 'checkConnection'])->name('check-connection');

            // Templates
            Route::get('/templates', [\App\Http\Controllers\WhatsAppHubController::class, 'getTemplates'])->name('templates');
            Route::put('/templates', [\App\Http\Controllers\WhatsAppHubController::class, 'saveTemplates'])->name('templates.save');
            Route::post('/templates/reset', [\App\Http\Controllers\WhatsAppHubController::class, 'resetTemplate'])->name('templates.reset');
            Route::post('/templates/preview', [\App\Http\Controllers\WhatsAppHubController::class, 'previewTemplate'])->name('templates.preview');

            // Whitelist Manager (Web UI)
            Route::prefix('whitelist')->name('whitelist.')->group(function () {
                Route::get('/', [\App\Http\Controllers\WhatsAppHubController::class, 'getWhitelist'])->name('index');
                Route::post('/', [\App\Http\Controllers\WhatsAppHubController::class, 'storeWhitelist'])->name('store');
                Route::patch('/{whitelist}/inventory-alert', [\App\Http\Controllers\WhatsAppHubController::class, 'updateWhitelistInventoryAlert'])
                    ->name('inventory-alert');
                Route::delete('/{whitelist}', [\App\Http\Controllers\WhatsAppHubController::class, 'destroyWhitelist'])->name('destroy');
            });
        });

        // AI Compose
        Route::post('/ai/compose', [\App\Http\Controllers\Api\AiController::class, 'compose'])->name('ai.compose');
    });

    // Redirects for backward compatibility
    Route::get('/tasks', function () {
        return redirect()->route('whatsapp.index', ['tab' => 'tasks']);
    })->name('tasks.index');

    Route::get('/broadcasts', function () {
        return redirect()->route('whatsapp.index', ['tab' => 'broadcasts']);
    })->middleware('can:manage-settings');

    Route::get('/reminders', function () {
        return redirect()->route('whatsapp.index', ['tab' => 'reminders']);
    })->middleware('can:manage-settings');

    // Staff Tasks (legacy routes removed or repurposed)
    // We keep specific task routes if they are used by AJAX calls from other modules, but main index is redirected.
    // For now, we assume the new WhatsAppHubController handles everything for tasks tab.

    /*
    // OLD TASKS ROUTES (Commented out to prevent conflict, logic moved to Hub)
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [StaffTaskController::class, 'index'])->name('index');
        Route::post('/', [StaffTaskController::class, 'store'])->name('store');
        ...
    });
    */

});

// Inventory Module Routes
Route::prefix('referensi/inventori')->name('inventory.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [App\Http\Controllers\Inventory\DashboardController::class, 'index'])->name('dashboard');

    // Items CRUD
    Route::resource('items', App\Http\Controllers\Inventory\ItemController::class);
    Route::get('items/{item}/lots', [App\Http\Controllers\Inventory\LotController::class, 'index'])->name('items.lots');
    Route::post('lots', [App\Http\Controllers\Inventory\LotController::class, 'store'])->name('lots.store');

    // Locations CRUD
    Route::resource('locations', App\Http\Controllers\Inventory\LocationController::class)->except(['show', 'destroy']);

    // Stock Card
    Route::get('kartu-stok', [App\Http\Controllers\Inventory\StockCardController::class, 'index'])->name('stock-card');
    Route::get('kartu-stok/cetak', [App\Http\Controllers\Inventory\StockCardController::class, 'print'])->name('stock-card.print');

    // Transactions
    Route::prefix('transaksi')->name('transaction.')->group(function () {
        Route::get('receipt', [App\Http\Controllers\Inventory\TransactionController::class, 'receiptForm'])->name('receipt');
        Route::post('receipt', [App\Http\Controllers\Inventory\TransactionController::class, 'receiptSubmit']);
        Route::get('issue', [App\Http\Controllers\Inventory\TransactionController::class, 'issueForm'])->name('issue');
        Route::post('issue', [App\Http\Controllers\Inventory\TransactionController::class, 'issueSubmit']);
        Route::get('transfer', [App\Http\Controllers\Inventory\TransactionController::class, 'transferForm'])->name('transfer');
        Route::post('transfer', [App\Http\Controllers\Inventory\TransactionController::class, 'transferSubmit']);
        Route::get('stocktake', [App\Http\Controllers\Inventory\TransactionController::class, 'stocktakeForm'])->name('stocktake');
        Route::post('stocktake', [App\Http\Controllers\Inventory\TransactionController::class, 'stocktakeSubmit']);
        Route::get('dispose', [App\Http\Controllers\Inventory\TransactionController::class, 'disposeForm'])->name('dispose');
        Route::post('dispose', [App\Http\Controllers\Inventory\TransactionController::class, 'disposeSubmit']);
    });

    // AJAX helpers
    Route::get('ajax/lots', [App\Http\Controllers\Inventory\TransactionController::class, 'getLotsForItem'])->name('ajax.lots');
    Route::get('ajax/balance', [App\Http\Controllers\Inventory\TransactionController::class, 'getBalanceForSelection'])->name('ajax.balance');
    Route::get('ajax/search', [App\Http\Controllers\Inventory\DashboardController::class, 'ajaxSearch'])->name('ajax.search');
    Route::get('ajax/overview', [App\Http\Controllers\Inventory\DashboardController::class, 'ajaxOverview'])->name('ajax.overview');
    Route::get('ajax/fast-moving', [App\Http\Controllers\Inventory\DashboardController::class, 'ajaxFastMoving'])->name('ajax.fast-moving');

    // Sample Disposal (Pemusnahan Sisa Sampel)
    Route::prefix('disposal')->name('disposal.')->group(function () {
        Route::get('/', [App\Http\Controllers\Inventory\SampleDisposalController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Inventory\SampleDisposalController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Inventory\SampleDisposalController::class, 'store'])->name('store');
        Route::get('/{disposal}', [App\Http\Controllers\Inventory\SampleDisposalController::class, 'show'])->name('show');
        Route::get('/{disposal}/pdf', [App\Http\Controllers\Inventory\SampleDisposalController::class, 'downloadPdf'])->name('pdf');
    });
});

// Label PDF Routes
Route::prefix('labels')->middleware(['auth'])->group(function () {
    // Evidence labels
    Route::get('evidence/request/{requestId}/sheet', [App\Http\Controllers\LabelController::class, 'evidenceSheet'])->name('labels.evidence.sheet');
    Route::get('evidence/{id}/single', [App\Http\Controllers\LabelController::class, 'evidenceSingle'])->name('labels.evidence.single');

    // Remaining labels
    Route::get('remaining/request/{requestId}/sheet', [App\Http\Controllers\LabelController::class, 'remainingSheet'])->name('labels.remaining.sheet');
    Route::get('remaining/{evidenceUnit}/all', [App\Http\Controllers\LabelController::class, 'remainingForEvidence'])->name('labels.remaining.all');
    Route::get('remaining/{id}/single', [App\Http\Controllers\LabelController::class, 'remainingSingle'])->name('labels.remaining.single');

    // Creation endpoints (AJAX)
    Route::post('evidence-units', [App\Http\Controllers\LabelController::class, 'createEvidenceUnits']);
    Route::post('remaining-units', [App\Http\Controllers\LabelController::class, 'createRemainingUnit']);
    Route::delete('remaining-units/{id}', [App\Http\Controllers\LabelController::class, 'destroyRemainingUnit']);
});

// Debug Routes (ONLY IN DEVELOPMENT)
if (app()->isLocal() || env('APP_DEBUG') === true) {
    Route::prefix('debug')->middleware('auth')->group(function () {

        Route::get('/doc-probe', [App\Http\Controllers\DebugDocController::class, 'probe'])
            ->name('debug.doc-probe');

        Route::get('/file-upload', function () {
            return response()->file(public_path('debug-file-upload.html'));
        })->name('debug.file-upload');

        Route::match(['get', 'post'], '/file-keys', function () {
            return response()->json([
                'message' => 'File input field names detected',
                'file_keys' => array_keys(\Illuminate\Support\Arr::dot(request()->allFiles())),
                'file_count' => count(request()->allFiles()),
                'all_input_keys' => array_keys(\Illuminate\Support\Arr::dot(request()->all())),
                'method' => request()->method(),
                'content_type' => request()->header('Content-Type'),
                'has_files' => request()->hasFile('samples'),
                'raw_files' => request()->allFiles(),
            ]);
        })->name('debug.file-keys');

        // QA debug routes for BA generation and document testing have been removed
    });
}

// Design Examples (for authenticated preview of design system)
Route::view('/design-examples', 'design-examples')
    ->middleware(['auth', 'verified'])
    ->name('design.examples');

require __DIR__.'/auth.php';
