<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use App\Enums\TestProcessStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormPreparationArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->user = User::factory()->create();
    }

    public function test_form_preparation_generates_pdf_and_stores_via_document_service(): void
    {
        // Arrange: Seed test data
        $investigator = Investigator::create([
            'nrp' => '97010977',
            'name' => 'Rizki Pratama',
            'rank' => 'IPTU',
            'jurisdiction' => 'Polda Metro Jaya',
            'phone' => '081234567890',
            'folder_key' => '97010977-rizki',
        ]);

        $testRequest = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $this->user->id,
            'request_number' => 'REQ-2025-0001',
            'to_office' => 'Pusdokkes Polri',
            'case_number' => 'BP/001/2025',
            'suspect_name' => 'Test Suspect',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $sample = Sample::create([
            'test_request_id' => $testRequest->id,
            'sample_code' => 'SP-001',
            'sample_name' => 'Tablet Putih Test',
            'sample_form' => 'pill',
            'test_methods' => json_encode(['uv_vis']),
            'active_substance' => 'MDMA',
            'package_quantity' => 10,
            'condition' => 'baik',
            'sample_status' => 'received',
        ]);

        $sampleProcess = SampleTestProcess::create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::PREPARATION->value,
            'performed_by' => $this->user->id,
            'started_at' => now(),
            'metadata' => json_encode(['method' => 'uv_vis']),
        ]);

        // Act: Hit GET /sample-processes/{id}/form/preparation
        $response = $this->actingAs($this->user)
            ->get(route('sample-processes.generate-form', [
                'sample_process' => $sampleProcess->id,
                'stage' => 'preparation'
            ]));

        // Assert: Response successful and is PDF
        $response->assertSuccessful();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Assert: Document created with type 'form_preparation'
        $document = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'form_preparation')
            ->where('source', 'generated')
            ->first();

        $this->assertNotNull($document, 'Document with type form_preparation should be created');
        $this->assertEquals('form_preparation', $document->document_type);
        $this->assertEquals('generated', $document->source);

        // Assert: Path contains "investigators/97010977-rizki/REQ-2025-0001/generated/form_preparation/"
        $expectedPathPattern = 'investigators/97010977-rizki/REQ-2025-0001/generated/form_preparation/';
        $this->assertStringContainsString(
            $expectedPathPattern,
            $document->path,
            "Document path should contain: {$expectedPathPattern}"
        );

        // Assert: Storage::disk('public')->assertExists($doc->path)
        Storage::disk('public')->assertExists($document->path);

        // Additional assertions for completeness
        $this->assertEquals($investigator->id, $document->investigator_id);
        $this->assertEquals($testRequest->id, $document->test_request_id);
        $this->assertStringContainsString('SP-001', $document->filename);
        $this->assertStringContainsString('REQ-2025-0001', $document->filename);
        $this->assertGreaterThan(0, $document->file_size);
    }

    public function test_form_preparation_download_parameter_works(): void
    {
        // Arrange
        $investigator = Investigator::create([
            'nrp' => '97010977',
            'name' => 'Rizki Pratama',
            'rank' => 'IPTU',
            'jurisdiction' => 'Polda Metro Jaya',
            'phone' => '081234567890',
            'folder_key' => '97010977-rizki',
        ]);

        $testRequest = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $this->user->id,
            'request_number' => 'REQ-2025-0001',
            'to_office' => 'Pusdokkes Polri',
            'suspect_name' => 'Test Suspect',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $sample = Sample::create([
            'test_request_id' => $testRequest->id,
            'sample_code' => 'SP-001',
            'sample_name' => 'Test Sample',
            'sample_form' => 'powder',
            'test_methods' => json_encode(['uv_vis']),
            'active_substance' => 'Test',
            'package_quantity' => 5,
            'condition' => 'baik',
            'sample_status' => 'received',
        ]);

        $sampleProcess = SampleTestProcess::create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::PREPARATION->value,
            'performed_by' => $this->user->id,
            'started_at' => now(),
            'metadata' => json_encode(['method' => 'uv_vis']),
        ]);

        // Act: Request with ?download=1 parameter
        $response = $this->actingAs($this->user)
            ->get(route('sample-processes.generate-form', [
                'sample_process' => $sampleProcess->id,
                'stage' => 'preparation'
            ]) . '?download=1');

        // Assert: Response is successful
        $response->assertSuccessful();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Assert: Document was created
        $document = Document::where('test_request_id', $testRequest->id)
            ->where('document_type', 'form_preparation')
            ->first();

        $this->assertNotNull($document);
        Storage::disk('public')->assertExists($document->path);
    }
}
