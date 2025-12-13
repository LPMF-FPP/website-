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

class DocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentService $documentService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        $this->documentService = app(DocumentService::class);
        
        // Bypass Gate authorization for testing
        \Illuminate\Support\Facades\Gate::before(function () {
            return true;
        });
    }

    /**
     * Test #1: Upload dokumen tersimpan per penyidik dan tercatat di database
     */
    public function test_upload_dokumen_tersimpan_per_penyidik_dan_tercatat_di_database(): void
    {
        // Arrange: Buat investigator dengan NRP dan nama yang fixed
        $investigator = Investigator::factory()->create([
            'nrp' => '87010123',
            'name' => 'Andri Wibowo',
        ]);

        $user = User::factory()->create();

        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        // Act: Upload file via DocumentService
        $file = UploadedFile::fake()->create(
            'Surat Permohonan.docx',
            10,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $document = $this->documentService->storeUpload(
            $file,
            $investigator,
            $testRequest,
            'request_letter'
        );

        // Assert: Document tercatat di database
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('request_letter', $document->document_type);
        $this->assertEquals('upload', $document->source);
        $this->assertEquals($investigator->id, $document->investigator_id);
        $this->assertEquals($testRequest->id, $document->test_request_id);

        // Assert: Path mengandung struktur folder yang benar
        $expectedPathPattern = "investigators/87010123-andri-wibowo/{$testRequest->request_number}/uploads/request_letter/";
        $this->assertStringContainsString($expectedPathPattern, $document->path);

        // Assert: File exists di storage
        Storage::disk('public')->assertExists($document->path);
    }

    /**
     * Test #2: File generated tersimpan per penyidik dengan path yang benar
     */
    public function test_file_generated_tersimpan_per_penyidik_dengan_path_yang_benar(): void
    {
        // Arrange: Buat investigator dan test request
        $investigator = Investigator::factory()->create([
            'nrp' => '87010123',
            'name' => 'Andri Wibowo',
        ]);

        $user = User::factory()->create();

        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        // Act: Buat binary content dan simpan via storeGenerated
        $binary = "%PDF-1.7\n%";
        $baseName = 'LHU-' . $testRequest->request_number;

        $document = $this->documentService->storeGenerated(
            $binary,
            'pdf',
            $investigator,
            $testRequest,
            'lhu',
            $baseName
        );

        // Assert: Document tercatat di database
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('lhu', $document->document_type);
        $this->assertEquals('generated', $document->source);
        $this->assertEquals($investigator->id, $document->investigator_id);
        $this->assertEquals($testRequest->id, $document->test_request_id);

        // Assert: Path mengandung struktur folder yang benar
        $expectedPathPattern = "investigators/87010123-andri-wibowo/{$testRequest->request_number}/generated/lhu/";
        $this->assertStringContainsString($expectedPathPattern, $document->path);

        // Assert: Filename mengandung "LHU-"
        $this->assertStringContainsString('LHU-', $document->filename);

        // Assert: File exists di storage
        Storage::disk('public')->assertExists($document->path);
    }

    /**
     * Test #3: Route download mengembalikan file dengan nama yang sesuai
     */
    public function test_route_download_mengembalikan_file_dengan_nama_yang_sesuai(): void
    {
        // Arrange: Buat document seperti pada test #2
        $investigator = Investigator::factory()->create([
            'nrp' => '87010123',
            'name' => 'Andri Wibowo',
        ]);

        $user = User::factory()->create();

        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        $binary = "%PDF-1.7\n%TEST CONTENT FOR DOWNLOAD";
        $baseName = 'LHU-' . $testRequest->request_number;

        $document = $this->documentService->storeGenerated(
            $binary,
            'pdf',
            $investigator,
            $testRequest,
            'lhu',
            $baseName
        );

        // Verify file was stored
        Storage::disk('public')->assertExists($document->path);
        
        // Get the actual file content from fake storage to verify
        $storedContent = Storage::disk('public')->get($document->path);
        $this->assertNotEmpty($storedContent);
        $this->assertEquals($binary, $storedContent);

        // Act: Hit download route with signed URL
        $signedUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'investigator.documents.download',
            ['document' => $document->id]
        );

        $response = $this->actingAs($user)->get($signedUrl);

        // Assert: Response 200
        $response->assertStatus(200);

        // Assert: Header content-disposition mengandung filename
        $response->assertHeader('content-disposition');
        $contentDisposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString($document->filename, $contentDisposition);

        // Assert: Response is a download response (BinaryFileResponse or StreamedResponse)
        $this->assertTrue(
            $response->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse ||
            $response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse,
            'Response should be a file download response'
        );
    }

    /**
     * Helper method: Get last created document from database
     */
    private function getLastDocument(): ?Document
    {
        return Document::latest('id')->first();
    }
}
