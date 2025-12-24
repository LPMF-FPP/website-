<?php

namespace Tests\Unit\Repositories;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Repositories\DocumentTemplateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DocumentTemplateRepository $repository;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DocumentTemplateRepository();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_create_template_version(): void
    {
        $template = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Test Template',
            'storage_path' => 'templates/test.blade.php',
            'checksum' => 'abc123',
            'is_active' => false,
            'meta' => ['test' => true],
        ]);

        $this->assertInstanceOf(DocumentTemplate::class, $template);
        $this->assertEquals(DocumentType::BA_PENERIMAAN, $template->type);
        $this->assertEquals(DocumentFormat::PDF, $template->format);
        $this->assertEquals(1, $template->version);
        $this->assertFalse($template->is_active);
    }

    public function test_version_increments_correctly(): void
    {
        // Create first version
        $template1 = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Template V1',
            'storage_path' => 'templates/test-v1.blade.php',
            'checksum' => 'abc123',
        ]);

        // Create second version
        $template2 = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Template V2',
            'storage_path' => 'templates/test-v2.blade.php',
            'checksum' => 'def456',
        ]);

        $this->assertEquals(1, $template1->version);
        $this->assertEquals(2, $template2->version);
    }

    public function test_only_one_template_can_be_active_per_type_and_format(): void
    {
        // Create and activate first template
        $template1 = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Template 1',
            'storage_path' => 'templates/test-1.blade.php',
            'checksum' => 'abc123',
            'is_active' => true,
        ]);

        // Create and activate second template (should deactivate first)
        $template2 = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Template 2',
            'storage_path' => 'templates/test-2.blade.php',
            'checksum' => 'def456',
            'is_active' => true,
        ]);

        $template1->refresh();
        $template2->refresh();

        $this->assertFalse($template1->is_active);
        $this->assertTrue($template2->is_active);
    }

    public function test_can_activate_template(): void
    {
        $template1 = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Template 1',
            'storage_path' => 'templates/test-1.blade.php',
            'checksum' => 'abc123',
            'is_active' => true,
        ]);

        $template2 = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Template 2',
            'storage_path' => 'templates/test-2.blade.php',
            'checksum' => 'def456',
            'is_active' => false,
        ]);

        // Activate template 2
        $this->repository->activateTemplate($template2->id);

        $template1->refresh();
        $template2->refresh();

        $this->assertFalse($template1->is_active);
        $this->assertTrue($template2->is_active);
    }

    public function test_can_get_active_template(): void
    {
        $template = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Active Template',
            'storage_path' => 'templates/active.blade.php',
            'checksum' => 'abc123',
            'is_active' => true,
        ]);

        $active = $this->repository->getActiveTemplate(
            DocumentType::BA_PENERIMAAN,
            DocumentFormat::PDF
        );

        $this->assertNotNull($active);
        $this->assertEquals($template->id, $active->id);
    }

    public function test_different_types_can_have_active_templates(): void
    {
        $template1 = $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'BA Penerimaan',
            'storage_path' => 'templates/ba-penerimaan.blade.php',
            'checksum' => 'abc123',
            'is_active' => true,
        ]);

        $template2 = $this->repository->createTemplateVersion([
            'type' => DocumentType::LHU->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'LHU',
            'storage_path' => 'templates/lhu.blade.php',
            'checksum' => 'def456',
            'is_active' => true,
        ]);

        $this->assertTrue($template1->fresh()->is_active);
        $this->assertTrue($template2->fresh()->is_active);
    }

    public function test_has_active_template_returns_correct_value(): void
    {
        $this->assertFalse(
            $this->repository->hasActiveTemplate(
                DocumentType::BA_PENERIMAAN,
                DocumentFormat::PDF
            )
        );

        $this->repository->createTemplateVersion([
            'type' => DocumentType::BA_PENERIMAAN->value,
            'format' => DocumentFormat::PDF->value,
            'name' => 'Test',
            'storage_path' => 'templates/test.blade.php',
            'checksum' => 'abc123',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->repository->hasActiveTemplate(
                DocumentType::BA_PENERIMAAN,
                DocumentFormat::PDF
            )
        );
    }
}
