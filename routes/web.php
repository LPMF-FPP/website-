<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SampleTestController;
use App\Http\Controllers\SampleTestProcessController;
use App\Http\Controllers\AnalystController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsPageController;
use App\Http\Controllers\Settings\TemplateController as SettingsTemplateController;
use App\Http\Controllers\Settings\NumberingController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Locale switch
Route::post('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Public Routes
Route::get('/health', [\App\Http\Controllers\HealthController::class, 'index'])->name('health');
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('landing');
});

// Public Tracking
Route::get('/track', [TrackingController::class, 'index'])->name('public.tracking');
Route::post('/track', [TrackingController::class, 'store'])->name('public.track');
Route::get('/track/{tracking_number}.json', [TrackingController::class, 'json'])->name('public.tracking.json');

// Authenticated Routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/dashboard-stats', [DashboardController::class, 'getStats'])->name('dashboard.stats');
    
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
    
    // Request document endpoints (sample_receipt, handover_report, request_letter_receipt)
    Route::get('/requests/{testRequest}/documents/{type}', [RequestController::class, 'downloadDocument'])->name('requests.documents.download');
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

    // Sample Testing
    Route::prefix('samples')->group(function () {
        Route::get('/test', [SampleTestController::class, 'create'])->name('samples.test.create');
        Route::post('/test', [SampleTestController::class, 'store'])->name('samples.test.store');
        Route::get('/test/{sampleDetail}', [SampleTestController::class, 'show'])->name('samples.test.show');
        Route::get('/', function() {
            return redirect()->route('samples.test.create');
        })->name('samples.index');
    });

    Route::resource('sample-processes', SampleTestProcessController::class);
    Route::get('sample-processes/{sample_process}/form/{stage}', [SampleTestProcessController::class, 'generateForm'])
        ->where('stage', '^(preparation|instrumentation)$')
        ->name('sample-processes.generate-form');
    Route::get('sample-processes/{sample_process}/lab-report', [SampleTestProcessController::class, 'generateReport'])
        ->name('sample-processes.lab-report');
    Route::post('samples/{sample}/ready-for-delivery', [SampleTestProcessController::class, 'markAsReadyForDelivery'])
        ->name('samples.ready-for-delivery');
    Route::resource('analysts', AnalystController::class)->except(['show']);

    // Delivery
    Route::prefix('delivery')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('delivery.index');
        Route::get('/{request}', [DeliveryController::class, 'show'])->name('delivery.show');
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

    // Statistics
    Route::prefix('statistics')->group(function () {
        Route::get('/', [StatisticsController::class, 'index'])->name('statistics.index');
        Route::get('/data', [StatisticsController::class, 'data'])->name('statistics.data');
        Route::get('/export', [StatisticsController::class, 'export'])->name('statistics.export');
    });

    Route::middleware('can:manage-settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsPageController::class, 'index'])->name('index');
        Route::get('/data', [SettingsController::class, 'show'])->name('show');
        Route::post('/save', [SettingsController::class, 'update'])->name('update');
        Route::post('/preview', [SettingsController::class, 'preview'])->name('preview');
        Route::post('/test', [SettingsController::class, 'test'])->name('test');
        Route::post('/brand-asset', [SettingsController::class, 'uploadBrandAsset'])->name('brand.upload');

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
        ->middleware('signed');
    Route::delete('/documents/{document}', [App\Http\Controllers\InvestigatorDocumentController::class, 'destroy'])
        ->name('investigator.documents.destroy');

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
