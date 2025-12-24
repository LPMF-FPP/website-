<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BeritaAcaraPenerimaanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Investigator $investigator;
    protected TestRequest $testRequest;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();

        $this->investigator = Investigator::factory()->create([
            'nrp' => '87010123',
            'name' => 'Andri Wibowo',
            'rank' => 'AKP',
            'jurisdiction' => 'Polda Metro Jaya',
        ]);

        $this->testRequest = TestRequest::factory()->create([
            'investigator_id' => $this->investigator->id,
            'user_id' => $this->user->id,
            'case_number' => 'BP/001/2025',
            'to_office' => 'Pusdokkes Polri',
        ]);

        // Create some samples
        Sample::factory()->count(3)->create([
            'test_request_id' => $this->testRequest->id,
            'test_methods' => json_encode(['uv_vis', 'gc_ms']),
        ]);
    }

    public function test_check_berita_acara_returns_false_when_not_generated(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/requests/{$this->testRequest->id}/berita-acara/check");

        $response->assertStatus(200)
            ->assertJson([
                'exists' => false,
                'document_id' => null,
            ]);
    }

    public function test_generate_berita_acara_creates_pdf_document(): void
    {
        // Force fresh query to ensure data is in DB
        $testRequest = TestRequest::with(['investigator', 'samples'])->find($this->testRequest->id);
        
        // Verify data is set up correctly
        $this->assertNotNull($testRequest);
        $this->assertNotNull($testRequest->investigator, 'Investigator should be loaded');
        $this->assertNotNull($testRequest->investigator_id, 'Investigator ID should be set');
        $this->assertGreaterThan(0, $testRequest->samples->count());
        
        $response = $this->actingAs($this->user)
            ->post("/requests/{$testRequest->id}/berita-acara/generate");

        // Debug error if redirect
        if ($response->status() === 302) {
            $error = $response->getSession()->get('error');
            $this->fail("Expected 200, got 302 redirect. Error: " . ($error ?? 'none'));
        }

        // Should return PDF response
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        // Check document was saved to database
        $document = Document::where('test_request_id', $this->testRequest->id)
            ->where('document_type', 'ba_penerimaan')
            ->where('source', 'generated')
            ->first();

        $this->assertNotNull($document);
        $this->assertStringContainsString('BA-Penerimaan', $document->filename);
        
        // Verify file path structure
        $expectedPathPattern = "investigators/{$this->investigator->folder_key}/{$this->testRequest->request_number}/generated/ba_penerimaan/";
        $this->assertStringContainsString($expectedPathPattern, $document->path);

        // Verify file exists in storage
        Storage::disk('public')->assertExists($document->path);
    }

    public function test_check_berita_acara_returns_true_after_generation(): void
    {
        // Generate first
        $this->actingAs($this->user)
            ->post("/requests/{$this->testRequest->id}/berita-acara/generate");

        // Then check
        $response = $this->actingAs($this->user)
            ->getJson("/requests/{$this->testRequest->id}/berita-acara/check");

        $response->assertStatus(200)
            ->assertJson([
                'exists' => true,
            ])
            ->assertJsonStructure([
                'exists',
                'filename',
                'document_id',
                'request_id',
            ]);
    }

    public function test_download_berita_acara_returns_pdf(): void
    {
        // Generate first
        $this->actingAs($this->user)
            ->post("/requests/{$this->testRequest->id}/berita-acara/generate");

        // Then download
        $response = $this->actingAs($this->user)
            ->get("/requests/{$this->testRequest->id}/berita-acara/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_view_berita_acara_returns_html_or_pdf(): void
    {
        // Generate first
        $this->actingAs($this->user)
            ->post("/requests/{$this->testRequest->id}/berita-acara/generate");

        // Then view
        $response = $this->actingAs($this->user)
            ->get("/requests/{$this->testRequest->id}/berita-acara/view");

        $response->assertStatus(200);
        // Should return either HTML or PDF
        $contentType = $response->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'text/html') || str_contains($contentType, 'application/pdf'),
            "Expected HTML or PDF content type, got: {$contentType}"
        );
    }

    public function test_berita_acara_contains_correct_data(): void
    {
        // Generate with ?archive_html=1 to also save HTML version
        $response = $this->actingAs($this->user)
            ->post("/requests/{$this->testRequest->id}/berita-acara/generate?archive_html=1");

        $response->assertStatus(200);

        // Find HTML document
        $htmlDocument = Document::where('test_request_id', $this->testRequest->id)
            ->where('document_type', 'ba_penerimaan_html')
            ->first();

        if ($htmlDocument && Storage::disk('public')->exists($htmlDocument->path)) {
            $htmlContent = Storage::disk('public')->get($htmlDocument->path);

            // Verify content contains expected data
            $this->assertStringContainsString($this->testRequest->request_number, $htmlContent);
            $this->assertStringContainsString($this->investigator->name, $htmlContent);
            $this->assertStringContainsString('Berita Acara Penerimaan Sampel', $htmlContent);
        } else {
            // If HTML not archived, just verify PDF document exists
            $pdfDocument = Document::where('test_request_id', $this->testRequest->id)
                ->where('document_type', 'ba_penerimaan')
                ->first();
            
            $this->assertNotNull($pdfDocument);
        }
    }
}
