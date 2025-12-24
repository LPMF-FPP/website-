<?php

namespace Tests\Feature\Api\Settings;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DocumentTemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Gate::define('manage-settings', fn() => true);
    }

    /** @test */
    public function it_returns_404_for_invalid_format()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/settings/document-templates/preview/ba_penerimaan/xlsx');

        $response->assertStatus(404)
            ->assertJsonStructure(['message', 'error'])
            ->assertJsonPath('message', 'Invalid format');
    }

    /** @test */
    public function it_returns_422_for_unsupported_format_by_type()
    {
        // BA Penerimaan doesn't support DOCX
        $response = $this->actingAs($this->user)
            ->getJson('/api/settings/document-templates/preview/ba_penerimaan/docx');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'error'])
            ->assertJsonPath('message', 'Format not supported');
    }

    /** @test */
    public function it_returns_422_for_invalid_document_type()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/settings/document-templates/preview/invalid_type/pdf');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /** @test */
    public function it_returns_pdf_for_valid_ba_penerimaan_request()
    {
        $response = $this->actingAs($this->user)
            ->get('/api/settings/document-templates/preview/ba_penerimaan/pdf');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // Verify PDF magic bytes
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
    }

    /** @test */
    public function it_returns_html_for_valid_lhu_html_request()
    {
        $response = $this->actingAs($this->user)
            ->get('/api/settings/document-templates/preview/lhu/html');

        $response->assertStatus(200);
        
        // Check content type (case insensitive)
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/html', strtolower($contentType));
        $this->assertStringContainsString('charset=utf-8', strtolower($contentType));

        $content = $response->getContent();
        $this->assertStringContainsString('<!DOCTYPE html>', $content);
    }

    /** @test */
    public function it_returns_pdf_for_valid_ba_penyerahan_request()
    {
        $response = $this->actingAs($this->user)
            ->get('/api/settings/document-templates/preview/ba_penyerahan/pdf');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/settings/document-templates/preview/ba_penerimaan/pdf');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_manage_settings_permission()
    {
        Gate::define('manage-settings', fn() => false);

        $response = $this->actingAs($this->user)
            ->getJson('/api/settings/document-templates/preview/ba_penerimaan/pdf');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_handles_case_sensitivity_in_format()
    {
        // Should fail because format validation is case-sensitive
        $response = $this->actingAs($this->user)
            ->getJson('/api/settings/document-templates/preview/ba_penerimaan/PDF');

        $response->assertStatus(422);
    }

    /** @test */
    public function it_includes_proper_error_messages_for_invalid_requests()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/settings/document-templates/preview/ba_penerimaan/xlsx');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Invalid format',
            ])
            ->assertJsonPath('error', 'Format must be one of: pdf, html, docx');
    }

    /** @test */
    public function it_can_preview_specific_template_html()
    {
        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'content_html' => '<div class="preview">Hello Preview</div>',
            'render_engine' => DocumentRenderEngine::DOMPDF,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/api/settings/document-templates/{$template->id}/preview/html");

        $response->assertOk();
        $this->assertStringContainsString('text/html', strtolower($response->headers->get('Content-Type')));
        $this->assertStringContainsString('Hello Preview', $response->getContent());
    }

    /** @test */
    public function it_can_preview_specific_template_pdf()
    {
        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'content_html' => '<div class="preview">PDF Preview</div>',
            'render_engine' => DocumentRenderEngine::DOMPDF,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/api/settings/document-templates/{$template->id}/preview/pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
    }
}
