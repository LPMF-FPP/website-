<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SampleTestController;
use App\Http\Controllers\SampleTestProcessController;
use App\Http\Controllers\AnalystController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsPageController;
use App\Http\Controllers\Settings\TemplateController;
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

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Lidik Sidik Index

    // Requests
    Route::resource('requests', RequestController::class);
    // Disabled: receipt document download/delete endpoints (sample_receipt, handover_report, request_letter_receipt)
    // Route::get('/requests/{request}/documents/{type}', [RequestController::class, 'downloadDocument'])->name('requests.documents.download');
    // Route::delete('/requests/{request}/documents/{type}', [RequestController::class, 'deleteDocument'])->name('requests.documents.delete');

    // Berita Acara Penerimaan
    Route::get('/requests/{request}/berita-acara/check', [RequestController::class, 'checkBeritaAcara'])
        ->name('requests.berita-acara.check');
    Route::post('/requests/{request}/berita-acara/generate', [RequestController::class, 'generateBeritaAcara'])
        ->name('requests.berita-acara.generate');
    Route::get('/requests/{request}/berita-acara/download', [RequestController::class, 'downloadBeritaAcara'])
        ->name('requests.berita-acara.download');
    Route::get('/requests/{request}/berita-acara/view', [RequestController::class, 'viewBeritaAcara'])
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

        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/activate', [TemplateController::class, 'activate'])->name('templates.activate');
    });

    Route::prefix('numbering')->name('numbering.')->group(function () {
        Route::post('/{scope}/preview', [NumberingController::class, 'preview'])->name('preview');
        Route::post('/{scope}/issue', [NumberingController::class, 'issue'])->name('issue');
    });

    // Database Documentation / Summary
    Route::middleware('can:view-database')->group(function () {
        Route::get('/database', [DatabaseController::class, 'index'])->name('database.index');
        Route::get('/database/suggest', [DatabaseController::class, 'suggest'])->name('database.suggest');
        // Separate routes for generated vs database documents
        Route::get('/database/docs/generated/download', [DatabaseController::class, 'download'])
            ->middleware('signed')
            ->name('database.docs.download.generated');
        Route::get('/database/docs/generated/preview', [DatabaseController::class, 'preview'])
            ->middleware('signed')
            ->name('database.docs.preview.generated');
        Route::get('/database/docs/{doc}/download', [DatabaseController::class, 'download'])
            ->middleware('signed')
            ->name('database.docs.download');
        Route::get('/database/docs/{doc}/preview', [DatabaseController::class, 'preview'])
            ->middleware('signed')
            ->name('database.docs.preview');
        Route::get('/database/request/{testRequest}/bundle', [DatabaseController::class, 'bundle'])
            ->name('database.request.bundle');
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

        // QA: Debug route to test BA generation
        Route::get('/ba/{id}', function ($id) {
            $testRequest = \App\Models\TestRequest::findOrFail($id);
            $controller = app(\App\Http\Controllers\RequestController::class);
            request()->merge(['download' => false]);
            return $controller->generateBeritaAcara($testRequest);
        })->name('debug.ba');

        // QA: Debug route to test DocumentService for SampleTestProcess
        Route::get('/process/{id}', function ($id) {
            $process = \App\Models\SampleTestProcess::with(['sample.testRequest.investigator'])
                ->findOrFail($id);

            $binary = "%PDF-1.7\n%DEBUG-TEST-DOCUMENT\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\nxref\n0 3\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\ntrailer<</Size 3/Root 1 0 R>>\nstartxref\n110\n%%EOF";

            $docs = app(\App\Services\DocumentService::class);
            $doc = $docs->storeForSampleProcess(
                $process,
                'pdf',
                'instrument_result',
                'DEBUG-' . $process->id,
                $binary
            );

            $info = [
                'success' => true,
                'process_id' => $process->id,
                'sample_id' => $process->sample->id,
                'sample_code' => $process->sample->sample_code,
                'request_id' => $process->sample->testRequest->id,
                'request_number' => $process->sample->testRequest->request_number,
                'investigator_id' => $process->sample->testRequest->investigator->id,
                'investigator_folder_key' => $process->sample->testRequest->investigator->folder_key,
                'document_id' => $doc->id,
                'document_type' => $doc->document_type,
                'document_filename' => $doc->filename,
                'document_path' => $doc->path,
                'storage_full_path' => storage_path('app/public/' . $doc->path),
                'file_exists' => \Illuminate\Support\Facades\Storage::disk('public')->exists($doc->path),
                'file_size' => strlen($binary),
            ];

            return response(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
                'Content-Type' => 'application/json',
            ]);
        })->name('debug.process');
    });
}


// Design Examples (for authenticated preview of design system)
Route::view('/design-examples', 'design-examples')
    ->middleware(['auth', 'verified'])
    ->name('design.examples');

require __DIR__.'/auth.php';
