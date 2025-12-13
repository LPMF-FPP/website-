<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadRouteTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentService $documentService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use real disk for this test since we're testing actual file download
        Storage::fake('public');
        $this->documentService = app(DocumentService::class);
        $this->user = User::factory()->create();
        
        // Bypass Gate authorization for testing
        \Illuminate\Support\Facades\Gate::before(function () {
            return true;
        });
    }

    public function test_document_can_be_downloaded_via_route(): void
    {
        // Arrange: Create investigator and test request
        $investigator = Investigator::factory()->create([
            'nrp' => '87010123',
            'name' => 'Andri Wibowo',
        ]);

        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $this->user->id,
        ]);

        // Create a document using DocumentService to ensure path is valid
        $file = UploadedFile::fake()->create(
            'Surat Permohonan.pdf',
            100,
            'application/pdf'
        );

        $document = $this->documentService->storeUpload(
            $file,
            $investigator,
            $testRequest,
            'request_letter'
        );

        // Verify file was stored
        Storage::disk('public')->assertExists($document->path);

        // Act: Generate signed URL and download
        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'investigator.documents.download',
            ['document' => $document->id]
        );

        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert: Check response
        $response->assertStatus(200);

        // Assert: Check content-disposition header contains filename
        $response->assertHeader('content-disposition');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString($document->filename, $contentDisposition);

        // Assert: Verify file exists in storage
        $this->assertTrue(Storage::disk('public')->exists($document->path));
    }

    public function test_generated_document_can_be_downloaded(): void
    {
        // Arrange: Create investigator and test request
        $investigator = Investigator::factory()->create([
            'nrp' => '88020456',
            'name' => 'Budi Santoso',
        ]);

        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $this->user->id,
        ]);

        // Create a generated document
        $pdfBinary = "%PDF-1.7\n%Test PDF Content for Download";
        
        $document = $this->documentService->storeGenerated(
            $pdfBinary,
            'pdf',
            $investigator,
            $testRequest,
            'lhu',
            'LHU-' . $testRequest->request_number
        );

        // Verify file was stored
        Storage::disk('public')->assertExists($document->path);

        // Act: Download with signed URL
        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'investigator.documents.download',
            ['document' => $document->id]
        );

        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert: Check response
        $response->assertStatus(200);

        // Assert: Check content-disposition header
        $response->assertHeader('content-disposition');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString($document->filename, $contentDisposition);

        // Assert: Verify file exists in storage
        $this->assertTrue(Storage::disk('public')->exists($document->path));
        
        // Assert: Verify content
        $storedContent = Storage::disk('public')->get($document->path);
        $this->assertEquals($pdfBinary, $storedContent);
    }

    public function test_document_download_requires_signed_url(): void
    {
        // Arrange: Create document
        $investigator = Investigator::factory()->create();
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $this->user->id,
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');
        $document = $this->documentService->storeUpload($file, $investigator, $testRequest, 'other');

        // Act: Try to access without signed URL
        $unsignedUrl = route('investigator.documents.download', ['document' => $document->id]);
        $response = $this->actingAs($this->user)->get($unsignedUrl);

        // Assert: Should fail without valid signature
        $response->assertStatus(403);
    }

    public function test_document_download_returns_correct_file_size(): void
    {
        // Arrange
        $investigator = Investigator::factory()->create();
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $this->user->id,
        ]);

        $binaryContent = "This is test content with specific size: " . str_repeat("A", 100);
        $document = $this->documentService->storeGenerated(
            $binaryContent,
            'txt',
            $investigator,
            $testRequest,
            'other',
            'test-file'
        );

        // Act
        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'investigator.documents.download',
            ['document' => $document->id]
        );
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        
        // Verify file size matches
        $this->assertEquals(strlen($binaryContent), $document->file_size);
        $this->assertEquals($document->file_size, Storage::disk('public')->size($document->path));
    }

    public function test_nonexistent_document_returns_404(): void
    {
        // Act: Try to download non-existent document
        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'investigator.documents.download',
            ['document' => 999999]
        );
        
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(404);
    }
}
