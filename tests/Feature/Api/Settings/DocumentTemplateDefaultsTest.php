<?php

namespace Tests\Feature\Api\Settings;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        // Mock Gate to allow manage-settings
        \Illuminate\Support\Facades\Gate::define('manage-settings', fn() => true);
    }

    /** @test */
    public function it_returns_default_templates_when_no_uploads_exist()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/document-templates');

        $response->assertOk();
        
        $data = $response->json();
        
        // Should have groups
        $this->assertArrayHasKey('groups', $data);
        $this->assertArrayHasKey('penerimaan', $data['groups']);
        $this->assertArrayHasKey('pengujian', $data['groups']);
        $this->assertArrayHasKey('penyerahan', $data['groups']);
        
        // Each group should have at least one default template
        $this->assertNotEmpty($data['groups']['penerimaan']);
        $this->assertNotEmpty($data['groups']['pengujian']);
        $this->assertNotEmpty($data['groups']['penyerahan']);
        
        // Defaults should be marked as active and default
        $penerimaanTemplate = collect($data['groups']['penerimaan'])->first();
        $this->assertTrue($penerimaanTemplate['is_active']);
        $this->assertTrue($penerimaanTemplate['is_default']);
        $this->assertEquals('ba_penerimaan', $penerimaanTemplate['type']);
    }

    /** @test */
    public function it_shows_uploaded_template_instead_of_default_when_exists()
    {
        // Create an uploaded template for BA Penerimaan
        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'name' => 'Custom BA Penerimaan',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/document-templates');

        $response->assertOk();
        
        $data = $response->json();
        
        // Should have the uploaded template
        $penerimaanTemplates = collect($data['groups']['penerimaan']);
        
        $customTemplate = $penerimaanTemplates->firstWhere('id', $template->id);
        $this->assertNotNull($customTemplate);
        $this->assertEquals('Custom BA Penerimaan', $customTemplate['name']);
        $this->assertTrue($customTemplate['is_active']);
        $this->assertFalse($customTemplate['is_default']);
        
        // Should not have default template for this type+format anymore
        $defaultTemplate = $penerimaanTemplates->firstWhere('is_default', true);
        $this->assertNull($defaultTemplate);
    }

    /** @test */
    public function it_maintains_only_one_active_template_per_type_format()
    {
        // Create two templates for same type+format
        $template1 = DocumentTemplate::factory()->create([
            'type' => DocumentType::LHU,
            'format' => DocumentFormat::PDF,
            'is_active' => true,
            'version' => 1,
        ]);

        $template2 = DocumentTemplate::factory()->create([
            'type' => DocumentType::LHU,
            'format' => DocumentFormat::PDF,
            'is_active' => false,
            'version' => 2,
        ]);

        // Activate template2
        $response = $this->withoutMiddleware()
            ->actingAs($this->admin)
            ->putJson("/api/settings/document-templates/{$template2->id}/activate");

        $response->assertOk();

        // Verify only template2 is active
        $template1->refresh();
        $template2->refresh();

        $this->assertFalse($template1->is_active);
        $this->assertTrue($template2->is_active);

        // Verify API returns only one active
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/document-templates');

        $lhuTemplates = collect($response->json('groups.pengujian'))
            ->where('format', 'pdf');

        $activeTemplates = $lhuTemplates->where('is_active', true);
        $this->assertCount(1, $activeTemplates);
    }

    /** @test */
    public function it_includes_preview_urls_for_all_templates()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settings/document-templates');

        $response->assertOk();
        
        $data = $response->json();
        
        // Check all groups have preview URLs
        foreach (['penerimaan', 'pengujian', 'penyerahan'] as $group) {
            $templates = collect($data['groups'][$group]);
            
            foreach ($templates as $template) {
                $this->assertArrayHasKey('preview_url', $template);
                $this->assertStringContainsString('/api/settings/document-templates/preview/', $template['preview_url']);
            }
        }
    }
}
