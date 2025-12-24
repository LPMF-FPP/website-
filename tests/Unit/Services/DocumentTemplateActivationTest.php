<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Repositories\DocumentTemplateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateActivationTest extends TestCase
{
    use RefreshDatabase;

    private DocumentTemplateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DocumentTemplateRepository();
    }

    /**
     * @test
     * Unit test: When activating a BA template, other BA templates should become is_active=false
     */
    public function activating_ba_template_deactivates_other_ba_templates(): void
    {
        // Create 3 BA Penerimaan templates
        $baTemplate1 = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'name' => 'BA Template v1',
            'is_active' => true,
            'version' => 1,
        ]);

        $baTemplate2 = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'name' => 'BA Template v2',
            'is_active' => false,
            'version' => 2,
        ]);

        $baTemplate3 = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'name' => 'BA Template v3',
            'is_active' => false,
            'version' => 3,
        ]);

        // Create LHU template (should not be affected)
        $lhuTemplate = DocumentTemplate::factory()->create([
            'type' => DocumentType::LHU,
            'format' => DocumentFormat::PDF,
            'name' => 'LHU Template',
            'is_active' => true,
            'version' => 1,
        ]);

        // Activate baTemplate2
        $this->repository->activateTemplate($baTemplate2->id);

        // Refresh models
        $baTemplate1->refresh();
        $baTemplate2->refresh();
        $baTemplate3->refresh();
        $lhuTemplate->refresh();

        // Assert: baTemplate2 is now active
        $this->assertTrue($baTemplate2->is_active, 'Activated template should be active');

        // Assert: baTemplate1 is now inactive (was active before)
        $this->assertFalse($baTemplate1->is_active, 'Previously active BA template should be deactivated');

        // Assert: baTemplate3 remains inactive
        $this->assertFalse($baTemplate3->is_active, 'Other inactive BA templates should remain inactive');

        // Assert: LHU template remains active (different type)
        $this->assertTrue($lhuTemplate->is_active, 'LHU template should not be affected by BA activation');

        // Assert: Only one BA template is active
        $activeBaCount = DocumentTemplate::where('type', DocumentType::BA_PENERIMAAN)
            ->where('is_active', true)
            ->count();
        $this->assertEquals(1, $activeBaCount, 'Only one BA template should be active');
    }

    /**
     * @test
     * Activating template via repository deactivates others of same type
     */
    public function activating_template_deactivates_same_type_only(): void
    {
        // Create BA Penerimaan templates with different versions
        $ba1 = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'version' => 1,
            'is_active' => true,
        ]);

        $ba2 = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'version' => 2,
            'is_active' => false,
        ]);

        // Create BA Penyerahan template (different type)
        $baPenyerahan = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENYERAHAN,
            'format' => DocumentFormat::PDF,
            'is_active' => true,
        ]);

        // Create LHU template (different type)
        $lhu = DocumentTemplate::factory()->create([
            'type' => DocumentType::LHU,
            'format' => DocumentFormat::PDF,
            'is_active' => true,
        ]);

        // Activate ba2
        $this->repository->activateTemplate($ba2->id);

        // Refresh
        $ba1->refresh();
        $ba2->refresh();
        $baPenyerahan->refresh();
        $lhu->refresh();

        // Assert
        $this->assertFalse($ba1->is_active, 'Same type template should be deactivated');
        $this->assertTrue($ba2->is_active, 'Newly activated template should be active');
        $this->assertTrue($baPenyerahan->is_active, 'Different type template should remain active');
        $this->assertTrue($lhu->is_active, 'Different type template should remain active');
    }

    /**
     * @test
     * Creating template with is_active=true deactivates existing active templates
     */
    public function creating_active_template_deactivates_existing(): void
    {
        $existing = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'is_active' => true,
        ]);

        $new = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'New Active Template',
            'content_html' => '<div>New</div>',
            'checksum' => 'new123',
            'is_active' => true,
        ]);

        $existing->refresh();

        $this->assertFalse($existing->is_active);
        $this->assertTrue($new->is_active);
    }

    /**
     * @test
     * Multiple templates can be inactive
     */
    public function multiple_templates_can_be_inactive(): void
    {
        // Create 3 templates with different versions to avoid unique constraint
        for ($i = 1; $i <= 3; $i++) {
            DocumentTemplate::factory()->create([
                'type' => DocumentType::BA_PENERIMAAN,
                'format' => DocumentFormat::PDF,
                'version' => $i,
                'is_active' => false,
            ]);
        }

        $inactiveCount = DocumentTemplate::where('type', DocumentType::BA_PENERIMAAN)
            ->where('is_active', false)
            ->count();

        $this->assertEquals(3, $inactiveCount);
    }

    /**
     * @test
     * Deactivating template works correctly
     */
    public function can_deactivate_template(): void
    {
        $template = DocumentTemplate::factory()->create([
            'type' => DocumentType::BA_PENERIMAAN,
            'format' => DocumentFormat::PDF,
            'is_active' => true,
        ]);

        $this->repository->deactivateTemplate($template->id);

        $template->refresh();

        $this->assertFalse($template->is_active);
    }
}
