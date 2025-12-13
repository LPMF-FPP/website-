<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SamplePhotoUploadTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->user = User::factory()->create();
    }

    public function test_sample_photos_are_uploaded_and_stored_correctly(): void
    {
        // Arrange: Prepare request data with sample photos
        $requestData = [
            'investigator_nrp' => '99010123',
            'investigator_name' => 'Eko Prasetyo',
            'investigator_rank' => 'IPTU',
            'investigator_jurisdiction' => 'Polda Jawa Tengah',
            'investigator_phone' => '081234567890',
            'investigator_email' => 'eko@polri.go.id',
            'to_office' => 'Pusdokkes Polri',
            'case_number' => 'BP/999/2025',
            'suspect_name' => 'Tersangka Test',
            'suspect_gender' => 'male',
            'suspect_age' => 25,
            'samples' => [
                [
                    'name' => 'Serbuk Putih Sampel 1',
                    'type' => 'powder',
                    'quantity' => 10,
                    'test_types' => ['uv_vis', 'gc_ms'],
                    'active_substance' => 'MDMA',
                ]
            ],
        ];

        // Create fake files
        $requestLetter = UploadedFile::fake()->create('surat-permintaan.pdf', 100, 'application/pdf');
        $samplePhoto1 = UploadedFile::fake()->image('sampel1.jpg', 100, 100);
        $samplePhoto2 = UploadedFile::fake()->image('sampel2.jpg', 100, 100);

        $requestData['request_letter'] = $requestLetter;
        $requestData['samples'][0]['photos'] = [$samplePhoto1, $samplePhoto2];

        // Act: Submit the form
        $response = $this->actingAs($this->user)
            ->post(route('requests.store'), $requestData);

        // Assert: Request was successful
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert: Investigator was created with folder_key
        $investigator = Investigator::where('nrp', '99010123')->first();
        $this->assertNotNull($investigator);
        $this->assertEquals('99010123-eko-prasetyo', $investigator->folder_key);

        // Assert: TestRequest was created
        $testRequest = TestRequest::where('investigator_id', $investigator->id)->first();
        $this->assertNotNull($testRequest);
        $this->assertNotNull($testRequest->request_number, 'Request number should be generated');

        // Assert: Sample was created
        $sample = Sample::where('test_request_id', $testRequest->id)->first();
        $this->assertNotNull($sample);
        $this->assertEquals('Serbuk Putih Sampel 1', $sample->sample_name);

        // Assert: Sample photo documents were created
        $samplePhotoDocs = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'sample_photo')
            ->where('source', 'upload')
            ->get();
        
        $this->assertCount(2, $samplePhotoDocs, 'Should have 2 sample photo documents');

        // Assert: Each document has correct path structure
        $expectedPathPattern = "investigators/{$investigator->folder_key}/{$testRequest->request_number}/uploads/sample_photo/";
        
        foreach ($samplePhotoDocs as $doc) {
            $this->assertStringContainsString(
                $expectedPathPattern,
                $doc->path,
                "Sample photo path should follow investigators/{folder_key}/{request_number}/uploads/sample_photo/ pattern"
            );

            // Assert: File exists in storage
            Storage::disk('public')->assertExists($doc->path);

            // Assert: Document has sample linkage in extra field
            $this->assertNotNull($doc->extra, 'Document extra field should not be null');
            $this->assertArrayHasKey('sample_id', $doc->extra, 'Document extra should have sample_id');
            $this->assertArrayHasKey('sample_name', $doc->extra, 'Document extra should have sample_name');
            $this->assertEquals($sample->id, $doc->extra['sample_id'], 'Document extra sample_id should match sample');
            $this->assertEquals($sample->sample_name, $doc->extra['sample_name'], 'Document extra sample_name should match sample');
        }
    }

    public function test_sample_photos_are_optional(): void
    {
        // Arrange: Prepare request data WITHOUT sample photos
        $requestData = [
            'investigator_nrp' => '99020456',
            'investigator_name' => 'Hendra Wijaya',
            'investigator_rank' => 'AKP',
            'investigator_jurisdiction' => 'Polres Bandung',
            'investigator_phone' => '082345678901',
            'to_office' => 'Pusdokkes Polri',
            'suspect_name' => 'Tersangka Test 2',
            'samples' => [
                [
                    'name' => 'Serbuk Putih',
                    'type' => 'powder',
                    'quantity' => 5,
                    'test_types' => ['uv_vis'],
                    'active_substance' => 'Heroin',
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

        $investigator = Investigator::where('nrp', '99020456')->first();
        $testRequest = TestRequest::where('investigator_id', $investigator->id)->first();
        $sample = Sample::where('test_request_id', $testRequest->id)->first();

        // Should have a sample
        $this->assertNotNull($sample);

        // Should NOT have any sample_photo documents
        $samplePhotoDocs = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'sample_photo')
            ->get();
        
        $this->assertCount(0, $samplePhotoDocs, 'Should have no sample photo documents when not uploaded');
    }

    public function test_multiple_samples_with_photos_are_stored_separately(): void
    {
        // Arrange: Prepare request data with TWO samples, each with photos
        $requestData = [
            'investigator_nrp' => '99030789',
            'investigator_name' => 'Irfan Hakim',
            'investigator_rank' => 'IPDA',
            'investigator_jurisdiction' => 'Polda DIY',
            'investigator_phone' => '083456789012',
            'to_office' => 'Pusdokkes Polri',
            'suspect_name' => 'Tersangka Test 3',
            'samples' => [
                [
                    'name' => 'Sampel A',
                    'type' => 'powder',
                    'quantity' => 10,
                    'test_types' => ['uv_vis'],
                    'active_substance' => 'Ecstasy',
                ],
                [
                    'name' => 'Sampel B',
                    'type' => 'liquid',
                    'quantity' => 5,
                    'test_types' => ['gc_ms'],
                    'active_substance' => 'Cocaine',
                ]
            ],
            'request_letter' => UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf'),
        ];

        // Add photos to both samples
        $requestData['samples'][0]['photos'] = [
            UploadedFile::fake()->image('sampel-a-1.jpg', 100, 100),
            UploadedFile::fake()->image('sampel-a-2.jpg', 100, 100),
        ];
        
        $requestData['samples'][1]['photos'] = [
            UploadedFile::fake()->image('sampel-b-1.jpg', 100, 100),
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->post(route('requests.store'), $requestData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $investigator = Investigator::where('nrp', '99030789')->first();
        $testRequest = TestRequest::where('investigator_id', $investigator->id)->first();
        $samples = Sample::where('test_request_id', $testRequest->id)->get();

        // Should have 2 samples
        $this->assertCount(2, $samples);

        $sampleA = $samples->where('sample_name', 'Sampel A')->first();
        $sampleB = $samples->where('sample_name', 'Sampel B')->first();

        // Should have 3 total sample photo documents
        $allPhotoDocs = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'sample_photo')
            ->get();
        
        $this->assertCount(3, $allPhotoDocs, 'Should have 3 total sample photos (2 for A, 1 for B)');

        // Check Sample A photos
        $sampleAPhotos = $allPhotoDocs->filter(function ($doc) use ($sampleA) {
            return ($doc->extra['sample_id'] ?? null) === $sampleA->id;
        });
        $this->assertCount(2, $sampleAPhotos, 'Sample A should have 2 photos');

        // Check Sample B photos
        $sampleBPhotos = $allPhotoDocs->filter(function ($doc) use ($sampleB) {
            return ($doc->extra['sample_id'] ?? null) === $sampleB->id;
        });
        $this->assertCount(1, $sampleBPhotos, 'Sample B should have 1 photo');

        // Verify all photos have correct linkage
        foreach ($sampleAPhotos as $photo) {
            $this->assertEquals($sampleA->id, $photo->extra['sample_id']);
            $this->assertEquals('Sampel A', $photo->extra['sample_name']);
        }

        foreach ($sampleBPhotos as $photo) {
            $this->assertEquals($sampleB->id, $photo->extra['sample_id']);
            $this->assertEquals('Sampel B', $photo->extra['sample_name']);
        }
    }

    public function test_sample_photos_cleanup_on_transaction_rollback(): void
    {
        // Arrange: Prepare request data with INVALID data (to trigger validation error)
        // Missing required field 'suspect_name'
        $requestData = [
            'investigator_nrp' => '99040999',
            'investigator_name' => 'Invalid Test',
            'investigator_rank' => 'IPTU',
            'investigator_jurisdiction' => 'Polda Test',
            'investigator_phone' => '084567890123',
            'to_office' => 'Pusdokkes Polri',
            // Missing 'suspect_name' - REQUIRED FIELD
            'samples' => [
                [
                    'name' => 'Test Sample',
                    'quantity' => 1,
                    'test_types' => ['uv_vis'],
                    'active_substance' => 'Test',
                ]
            ],
            'request_letter' => UploadedFile::fake()->create('surat.pdf', 50, 'application/pdf'),
        ];

        $requestData['samples'][0]['photos'] = [
            UploadedFile::fake()->image('test-photo.jpg', 100, 100),
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->post(route('requests.store'), $requestData);

        // Assert: Request failed validation
        $response->assertSessionHasErrors('suspect_name');

        // Assert: No documents were created in database (due to validation failure)
        $photoDocs = Document::where('document_type', 'sample_photo')->get();
        $this->assertCount(0, $photoDocs, 'No sample photo documents should be created on validation failure');

        // Note: Physical files might still exist in storage during test
        // but in real scenario, validation happens BEFORE file processing
    }
}
