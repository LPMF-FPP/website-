<?php

namespace Tests\Feature\Api\Settings;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

class DocumentTemplateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create();
        Gate::define('manage-settings', fn() => true);
    }

    /**
     * @test
     * Feature test: Complete workflow - create, update draft, activate, preview
     */
    public function complete_template_workflow_with_html_payload(): void
    {
        $this->actingAs($this->admin);

        // Step 1: Create template with HTML payload (without Blade syntax to avoid rendering issues in preview)
        $createPayload = [
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'BA Penerimaan Template v1',
            'content_html' => '<div class="document"><h1>Berita Acara Penerimaan</h1><p>Nomor: BA-001</p></div>',
            'content_css' => 'body { font-family: Arial; font-size: 12px; } .document { padding: 20px; }',
            'is_active' => false,
        ];

        $createResponse = $this->postJson('/api/settings/document-templates', $createPayload);

        $createResponse->assertCreated()
            ->assertJsonPath('template.name', 'BA Penerimaan Template v1')
            ->assertJsonPath('template.is_active', false)
            ->assertJsonPath('template.version', 1);

        $templateId = $createResponse->json('template.id');

        $this->assertDatabaseHas('document_templates', [
            'id' => $templateId,
            'type' => DocumentType::BA_PENERIMAAN->value,
            'name' => 'BA Penerimaan Template v1',
            'is_active' => false,
        ]);

        // Step 2: Update draft (save updated HTML/CSS)
        $updatePayload = [
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'BA Penerimaan Template v1.1',
            'content_html' => '<div class="document"><h1>Berita Acara Penerimaan Barang Bukti</h1><p>Nomor: BA-002</p><p>Tanggal: 22 Desember 2025</p></div>',
            'content_css' => 'body { font-family: Arial; font-size: 12px; color: #333; } .document { padding: 20px; border: 1px solid #ccc; }',
        ];

        $updateResponse = $this->putJson("/api/settings/document-templates/{$templateId}", $updatePayload);

        $updateResponse->assertOk()
            ->assertJsonPath('template.name', 'BA Penerimaan Template v1.1')
            ->assertJsonPath('template.version', 2);

        $newTemplateId = $updateResponse->json('template.id');
        $this->assertNotEquals($templateId, $newTemplateId, 'Update should create new version');

        $this->assertDatabaseHas('document_templates', [
            'id' => $newTemplateId,
            'name' => 'BA Penerimaan Template v1.1',
            'version' => 2,
        ]);

        // Step 3: Activate the new version
        $activateResponse = $this->putJson("/api/settings/document-templates/{$newTemplateId}/activate");

        $activateResponse->assertOk()
            ->assertJsonPath('message', 'Template activated successfully')
            ->assertJsonPath('template.is_active', true);

        $this->assertDatabaseHas('document_templates', [
            'id' => $newTemplateId,
            'is_active' => true,
        ]);

        // Step 4: Preview returns 200 and Content-Type: application/pdf
        // Note: Preview uses DocumentTemplateRenderService which calls PdfRenderService
        // We'll test just that the endpoint returns success and correct content type
        // Actual PDF generation is tested in dedicated preview tests

        $template = DocumentTemplate::find($newTemplateId);
        
        // Test HTML preview (doesn't require PDF rendering)
        $htmlPreviewResponse = $this->actingAs($this->admin)
            ->get("/api/settings/document-templates/{$template->id}/preview/html");

        $htmlPreviewResponse->assertOk();
        $contentType = $htmlPreviewResponse->headers->get('Content-Type');
        $this->assertStringContainsString('text/html', strtolower($contentType));
    }

    /**
     * @test
     * Preview HTML returns 200 and text/html content type
     */
    public function preview_html_returns_correct_content_type(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'name' => 'Test Template',
            'content_html' => '<html><body><h1>Test</h1></body></html>',
            'render_engine' => DocumentRenderEngine::DOMPDF,
            'is_active' => true,
        ]);

        $response = $this->get("/api/settings/document-templates/{$template->id}/preview/html");

        $response->assertOk();
        
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/html', strtolower($contentType));
    }

    /**
     * @test
     * General preview endpoint with mocked PDF service
     */
    public function general_preview_endpoint_returns_pdf(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'name' => 'Preview PDF Template',
            'content_html' => '<html><body><h1>Preview PDF</h1></body></html>',
            'render_engine' => DocumentRenderEngine::DOMPDF,
            'is_active' => true,
        ]);

        $response = $this->get("/api/settings/document-templates/{$template->id}/preview/pdf");

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', strtolower($contentType));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
