<?php

namespace Tests\Feature\Api\Settings;

use App\Enums\DocumentFormat;
use App\Enums\DocumentRenderEngine;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DocumentTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create();
        // Assume admin has manage-settings permission
        Gate::define('manage-settings', fn() => true);
    }

    public function test_can_list_templates(): void
    {
        $this->actingAs($this->admin);

        // Create some templates
        DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/settings/document-templates');

        $response->assertOk()
            ->assertJsonStructure([
                'groups',
                'documentTypes',
            ]);
    }

    public function test_can_upload_template(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('template.blade.php', 10, 'text/plain');

        $response = $this->postJson('/api/settings/document-templates/upload', [
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Test Template',
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'type',
                'format',
                'name',
                'version',
                'is_active',
            ]);

        $this->assertDatabaseHas('document_templates', [
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Test Template',
        ]);
    }

    public function test_can_create_template_via_editor_payload(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Grapes Template',
            'content_html' => '<div class="doc">Preview</div>',
            'content_css' => 'body { font-size: 12px; }',
            'render_engine' => DocumentRenderEngine::DOMPDF->value,
        ];

        $response = $this->postJson('/api/settings/document-templates', $payload);

        $response->assertCreated()
            ->assertJsonPath('template.name', 'Grapes Template');

        $this->assertDatabaseHas('document_templates', [
            'name' => 'Grapes Template',
            'type' => DocumentType::BA_PENERIMAAN->value,
            'content_html' => '<div class="doc">Preview</div>',
        ]);
    }

    public function test_can_update_template_via_editor_payload(): void
    {
        $this->actingAs($this->admin);
        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'content_html' => '<div>Old</div>',
            'render_engine' => DocumentRenderEngine::DOMPDF,
        ]);

        $payload = [
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Updated Template',
            'content_html' => '<div>New Content</div>',
            'content_css' => 'body { color: #111; }',
            'render_engine' => DocumentRenderEngine::DOMPDF->value,
        ];

        $response = $this->putJson("/api/settings/document-templates/{$template->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('template.name', 'Updated Template');

        $this->assertDatabaseHas('document_templates', [
            'name' => 'Updated Template',
            'content_html' => '<div>New Content</div>',
        ]);

        $this->assertNotEquals($template->id, $response->json('template.id'));
    }

    public function test_can_activate_template(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'is_active' => false,
        ]);

        $response = $this->putJson("/api/settings/document-templates/{$template->id}/activate");

        $response->assertOk()
            ->assertJson([
                'message' => 'Template activated successfully',
            ]);

        $this->assertDatabaseHas('document_templates', [
            'id' => $template->id,
            'is_active' => true,
        ]);
    }

    public function test_activating_template_deactivates_others(): void
    {
        $this->actingAs($this->admin);

        $template1 = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'is_active' => true,
        ]);

        $template2 = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'is_active' => false,
        ]);

        $this->putJson("/api/settings/document-templates/{$template2->id}/activate");

        $this->assertDatabaseHas('document_templates', [
            'id' => $template1->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('document_templates', [
            'id' => $template2->id,
            'is_active' => true,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/settings/document-templates');
        
        $response->assertUnauthorized();
    }
}
