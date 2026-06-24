<?php

namespace Tests\Feature;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Enums\SampleStatus;
use App\Enums\TestProcessStage;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use App\Models\UserGoogleDriveToken;
use App\Services\GoogleDriveDocumentSyncService;
use App\Services\GoogleDriveOAuthService;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class LhuGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lhu_generated_automatically_on_interpretation_complete()
    {
        Storage::fake('public');

        // Mock PdfRenderService to prevent slow PDF generation
        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturn('%PDF-1.4 mock pdf content');
        });

        // Setup user
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        // Setup sample and request
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);

        // Create interpretation process
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        // Mock DocumentService to ensure createLhuDocument is called and succeeds
        // However, since we are testing the full flow including the controller,
        // and the controller resolves services from the container,
        // we should rely on the real services or mock them in the container if needed.
        // For a Feature test, using real services (with faked storage) is better for integration testing.

        // Act: Update with completion data (test_result is positive/negative)
        $response = $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
            ]);

        // Assert response is redirect
        $response->assertRedirect();

        // Assert LHU document exists in database
        $this->assertDatabaseHas('documents', [
            'test_request_id' => $testRequest->id,
            'document_type' => 'laporan_hasil_uji',
        ]);
        $document = Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('document_type', 'laporan_hasil_uji')
            ->firstOrFail();
        $this->assertSame('skipped', data_get($document->extra, 'google_drive.status'));

        $htmlDocument = Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('document_type', 'laporan_hasil_uji_html')
            ->firstOrFail();

        $html = Storage::disk($htmlDocument->storage_disk ?? 'public')->get($htmlDocument->path);
        $this->assertStringContainsString('&ldquo;Pro Justitia&rdquo;', $html);

        // Assert process metadata has lhu_number
        $process->refresh();
        $this->assertNotNull($process->metadata['lhu_number'] ?? null);
    }

    public function test_lhu_does_not_include_pro_justitia_for_non_polri_request(): void
    {
        Storage::fake('public');

        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturn('%PDF-1.4 mock pdf content');
        });

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        $investigator = Investigator::factory()->create([
            'is_polri' => false,
            'rank' => 'Pemohon',
            'nrp' => 'EXT0000001',
        ]);

        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
        ]);
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $htmlDocument = Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('document_type', 'laporan_hasil_uji_html')
            ->firstOrFail();

        $html = Storage::disk($htmlDocument->storage_disk ?? 'public')->get($htmlDocument->path);
        $this->assertStringNotContainsString('&ldquo;Pro Justitia&rdquo;', $html);
    }

    public function test_active_lhu_template_injects_pro_justitia_for_polri_request(): void
    {
        Storage::fake('public');

        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturn('%PDF-1.4 mock pdf content');
        });

        DocumentTemplate::factory()->create([
            'type' => DocumentType::LHU,
            'format' => DocumentFormat::HTML,
            'doc_type' => 'LHU',
            'status' => 'issued',
            'is_active' => true,
            'content_html' => '<div class="doc-template"><h1>LAPORAN HASIL UJI</h1><p>Nomor: {{report_number}}</p></div>',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $htmlDocument = Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('document_type', 'laporan_hasil_uji_html')
            ->firstOrFail();

        $html = Storage::disk($htmlDocument->storage_disk ?? 'public')->get($htmlDocument->path);
        $this->assertStringContainsString('&ldquo;Pro Justitia&rdquo;', $html);
    }

    public function test_lhu_generation_fails_gracefully_on_error()
    {
        Storage::fake('public');

        // Mock PdfRenderService to prevent slow PDF generation
        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturn('%PDF-1.4 mock pdf content');
        });

        // Setup user
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        // Setup sample and request
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);

        // Create interpretation process
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        // Mock DocumentService to throw an exception
        $this->mock(\App\Services\DocumentService::class, function ($mock) {
            $mock->shouldReceive('storeForSampleProcess')->andThrow(new \Exception('Simulated PDF generation failure'));
        });

        // Act: Update with completion data
        $response = $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
            ]);

        // Assert response is still redirect (success) because error is logged but not blocking
        $response->assertRedirect();

        // Assert LHU document does NOT exist (failed)
        $this->assertDatabaseMissing('documents', [
            'test_request_id' => $testRequest->id,
            'document_type' => 'laporan_hasil_uji',
        ]);

        // Ensure the process was still updated despite LHU failure
        $process->refresh();
        $this->assertEquals('Metamfetamina', $process->metadata['detected_substance']);
    }

    public function test_uploaded_test_result_attachment_is_stored_as_document_for_google_drive_sync(): void
    {
        Storage::fake('public');

        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturn('%PDF-1.4 mock pdf content');
        });

        /** @var User $user */
        $user = User::factory()->create(['role' => 'analis']);
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        $file = UploadedFile::fake()->create('hasil-pengujian.pdf', 128, 'application/pdf');

        $response = $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
                'test_result_file' => $file,
            ]);

        $response->assertRedirect();

        $document = Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('sample_id', $sample->id)
            ->where('document_type', 'test_results')
            ->firstOrFail();

        Storage::disk('public')->assertExists($document->path);

        $process->refresh();
        $this->assertSame($document->path, $process->metadata['test_result_attachment_path'] ?? null);
        $this->assertSame('hasil-pengujian.pdf', $process->metadata['test_result_attachment_original'] ?? null);
        $this->assertSame('skipped', data_get($document->fresh()->extra, 'google_drive.status'));
    }

    public function test_replacing_test_result_attachment_removes_previous_document_and_file(): void
    {
        Storage::fake('public');

        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturn('%PDF-1.4 mock pdf content');
        });

        /** @var User $user */
        $user = User::factory()->create(['role' => 'analis']);
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
                'test_result_file' => UploadedFile::fake()->create('hasil-lama.pdf', 128, 'application/pdf'),
            ])
            ->assertRedirect();

        $oldDocument = Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('sample_id', $sample->id)
            ->where('document_type', 'test_results')
            ->firstOrFail();
        $oldPath = $oldDocument->path;

        $this->actingAs($user)
            ->put(route('testing.processes.update', $process->fresh()), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
                'test_result_file' => UploadedFile::fake()->create('hasil-baru.pdf', 128, 'application/pdf'),
            ])
            ->assertRedirect();

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertDatabaseMissing('documents', ['id' => $oldDocument->id]);
        $this->assertSame(1, Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('sample_id', $sample->id)
            ->where('document_type', 'test_results')
            ->count());
    }

    public function test_google_drive_uses_sample_code_for_lhu_and_test_result_filenames(): void
    {
        $service = new GoogleDriveDocumentSyncService(
            $this->createMock(GoogleDriveOAuthService::class),
            $this->createMock(GoogleDriveService::class),
        );
        $method = (new ReflectionClass($service))->getMethod('driveFilenameFor');
        $method->setAccessible(true);

        $sample = new Sample(['sample_code' => 'LS001I2026']);
        $attachment = new Document([
            'document_type' => 'test_results',
            'original_filename' => 'hasil.pdf',
        ]);
        $attachment->setRelation('sample', $sample);
        $lhu = new Document([
            'document_type' => 'laporan_hasil_uji',
            'original_filename' => 'lhu.pdf',
        ]);
        $lhu->setRelation('sample', $sample);

        $this->assertSame('Lampiran Pengujian - LS001I2026.pdf', $method->invoke($service, $attachment, 'fallback.pdf'));
        $this->assertSame('LHU - LS001I2026.pdf', $method->invoke($service, $lhu, 'fallback.pdf'));
    }

    public function test_google_drive_uses_month_receipt_and_suspect_folder_by_default(): void
    {
        settings_fake_clear();
        settings_forget_cache();

        $service = new GoogleDriveDocumentSyncService(
            $this->createMock(GoogleDriveOAuthService::class),
            $this->createMock(GoogleDriveService::class),
        );
        $method = (new ReflectionClass($service))->getMethod('folderPathFor');
        $method->setAccessible(true);

        $testRequest = TestRequest::factory()->make([
            'receipt_number' => 'RESI-2026-0001',
            'request_number' => 'REQ-2026-0001',
            'suspect_name' => 'Budi Santoso',
            'created_at' => now()->setDate(2026, 4, 15),
        ]);
        $document = new Document(['document_type' => 'request_letter']);
        $document->setRelation('testRequest', $testRequest);

        $this->assertStringContainsString('2026-04/RESI-2026-0001 - Budi Santoso/Permintaan', $method->invoke($service, $document));
    }

    public function test_google_drive_uses_configured_uploader_user_when_available(): void
    {
        settings_fake_clear();
        settings_forget_cache();

        $currentUser = User::factory()->create(['is_active' => true]);
        $uploader = User::factory()->create(['is_active' => true]);
        UserGoogleDriveToken::create([
            'user_id' => $uploader->id,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
        ]);
        settings_fake(['google_drive.uploader_user_id' => $uploader->id], true);

        $service = new GoogleDriveDocumentSyncService(
            $this->createMock(GoogleDriveOAuthService::class),
            $this->createMock(GoogleDriveService::class),
        );
        $method = (new ReflectionClass($service))->getMethod('syncUsersFor');
        $method->setAccessible(true);

        $candidates = $method->invoke($service, $currentUser);

        $this->assertIsArray($candidates);
        $this->assertSame($uploader->id, $candidates[0]?->id ?? null);
    }
}
