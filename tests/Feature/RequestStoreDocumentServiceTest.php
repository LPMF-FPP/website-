<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestStoreDocumentServiceTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->user = User::factory()->create();
    }

    public function test_request_store_uses_document_service_for_uploads(): void
    {
        // Arrange: Prepare request data
        $requestData = [
            'investigator_nrp' => '87010123',
            'investigator_name' => 'Andri Wibowo',
            'investigator_rank' => 'AKP',
            'investigator_jurisdiction' => 'Polda Metro Jaya',
            'investigator_phone' => '081234567890',
            'investigator_email' => 'andri@polri.go.id',
            'to_office' => 'Pusdokkes Polri',
            'case_number' => 'BP/001/2025',
            'suspect_name' => 'Test Suspect',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'samples' => [
                [
                    'name' => 'Sample A',
                    'type' => 'powder',
                    'quantity' => 10,
                    'test_types' => ['uv_vis', 'gc_ms'],
                    'active_substance' => 'Methamphetamine',
                ]
            ],
        ];

        // Create fake files
        $requestLetter = UploadedFile::fake()->create('surat-permintaan.pdf', 100, 'application/pdf');
        $evidencePhoto = UploadedFile::fake()->image('barang-bukti.jpg', 800, 600);

        $requestData['request_letter'] = $requestLetter;
        $requestData['evidence_photo'] = $evidencePhoto;

        // Act: Submit the form
        $response = $this->actingAs($this->user)
            ->post(route('requests.store'), $requestData);

        // Assert: Request was successful
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert: Investigator was created with folder_key
        $investigator = Investigator::where('nrp', '87010123')->first();
        $this->assertNotNull($investigator);
        $this->assertEquals('87010123-andri-wibowo', $investigator->folder_key);

        // Assert: TestRequest was created
        $testRequest = TestRequest::where('investigator_id', $investigator->id)->first();
        $this->assertNotNull($testRequest);

        // Assert: Documents were created in documents table
        $letterDoc = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'request_letter')
            ->where('source', 'upload')
            ->first();
        
        $this->assertNotNull($letterDoc, 'Request letter document should be created');

        $evidenceDoc = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'evidence_photo')
            ->where('source', 'upload')
            ->first();
        
        $this->assertNotNull($evidenceDoc, 'Evidence photo document should be created');

        // Assert: Paths follow correct structure
        $expectedPathPattern = "investigators/87010123-andri-wibowo/{$testRequest->request_number}/uploads/";
        
        $this->assertStringContainsString(
            $expectedPathPattern . 'request_letter/',
            $letterDoc->path,
            'Request letter path should follow investigators/{folder_key}/{request_number}/uploads/request_letter/ pattern'
        );

        $this->assertStringContainsString(
            $expectedPathPattern . 'evidence_photo/',
            $evidenceDoc->path,
            'Evidence photo path should follow investigators/{folder_key}/{request_number}/uploads/evidence_photo/ pattern'
        );

        // Assert: Files exist in storage
        Storage::disk('public')->assertExists($letterDoc->path);
        Storage::disk('public')->assertExists($evidenceDoc->path);

        // Assert: TestRequest columns are updated with Document paths
        $this->assertEquals($letterDoc->path, $testRequest->official_letter_path);
        $this->assertEquals($evidenceDoc->path, $testRequest->evidence_photo_path);
    }

    public function test_request_store_without_evidence_photo_still_works(): void
    {
        // Arrange: Prepare request data without evidence photo
        $requestData = [
            'investigator_nrp' => '88020456',
            'investigator_name' => 'Budi Santoso',
            'investigator_rank' => 'IPDA',
            'investigator_jurisdiction' => 'Polres Jakarta Pusat',
            'investigator_phone' => '082345678901',
            'to_office' => 'Pusdokkes Polri',
            'suspect_name' => 'Test Suspect 2',
            'samples' => [
                [
                    'name' => 'Sample B',
                    'test_types' => ['uv_vis'],
                    'active_substance' => 'Cannabis',
                    'quantity' => 5,
                ]
            ],
            'request_letter' => UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf'),
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->post(route('requests.store'), $requestData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $investigator = Investigator::where('nrp', '88020456')->first();
        $testRequest = TestRequest::where('investigator_id', $investigator->id)->first();

        // Should have request_letter document
        $letterDoc = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'request_letter')
            ->first();
        $this->assertNotNull($letterDoc);

        // Should NOT have evidence_photo document
        $evidenceDoc = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'evidence_photo')
            ->first();
        $this->assertNull($evidenceDoc);

        // TestRequest should have official_letter_path but not evidence_photo_path
        $this->assertNotNull($testRequest->official_letter_path);
        $this->assertNull($testRequest->evidence_photo_path);
    }

    public function test_investigator_folder_key_is_generated_if_empty(): void
    {
        // Arrange: Create investigator without folder_key
        // Note: Investigator model auto-generates folder_key in boot() method
        $investigator = Investigator::create([
            'nrp' => '89030789',
            'name' => 'Test Investigator',
            'rank' => 'IPTU',
            'jurisdiction' => 'Test Jurisdiction',
            'phone' => '083456789012',
        ]);

        // folder_key should already be generated by model
        $this->assertNotNull($investigator->folder_key);
        $this->assertEquals('89030789-test-investigator', $investigator->folder_key);

        // Arrange: Prepare request
        $requestData = [
            'investigator_nrp' => '89030789', // Will match existing investigator
            'investigator_name' => 'Test Investigator',
            'investigator_rank' => 'IPTU',
            'investigator_jurisdiction' => 'Test Jurisdiction',
            'investigator_phone' => '083456789012',
            'to_office' => 'Pusdokkes Polri',
            'suspect_name' => 'Test Suspect 3',
            'samples' => [
                [
                    'name' => 'Sample C',
                    'test_types' => ['gc_ms'],
                    'active_substance' => 'Heroin',
                    'quantity' => 3,
                ]
            ],
            'request_letter' => UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf'),
        ];

        // Act: Submit request
        $response = $this->actingAs($this->user)
            ->post(route('requests.store'), $requestData);

        // Assert: Request successful
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert: folder_key remains the same (already generated)
        $investigator->refresh();
        $this->assertNotNull($investigator->folder_key);
        $this->assertEquals('89030789-test-investigator', $investigator->folder_key);

        // Assert: Document uses the existing folder_key in path
        $testRequest = TestRequest::where('investigator_id', $investigator->id)->first();
        $document = Document::where('test_request_id', $testRequest->id)->first();
        
        $this->assertStringContainsString('investigators/89030789-test-investigator/', $document->path);
    }
}
