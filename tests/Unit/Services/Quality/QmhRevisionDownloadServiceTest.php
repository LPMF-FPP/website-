<?php

namespace Tests\Unit\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\User;
use App\Services\Quality\QmhRevisionDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhRevisionDownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_watermarked_html_contains_expected_watermark_and_version_label(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create([
            'name' => 'Gifari',
            'role' => 'admin',
            'rank' => 'Penata TK I',
            'nrp' => '12345678',
            'nip' => '19876543210001',
        ]);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-DL-001',
            'title' => 'Dokumen Download Test',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 2,
            'revision_number' => 4,
            'version_label' => 'E2-R4',
            'status' => 'published',
            'form_schema_json' => [
                'version' => 1,
                'doc_type' => 'sop',
                'questions' => [],
            ],
            'content_html' => '<p>Konten PDF uji watermark.</p>',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        $service = new QmhRevisionDownloadService;
        $html = $service->buildWatermarkedHtml($revision, 'SALINAN TERKENDALI');

        $this->assertStringContainsString('SALINAN TERKENDALI', $html);
        $this->assertStringContainsString('E2/R4', $html);
        $this->assertStringContainsString('Konten PDF uji watermark.', $html);

        // Structured header/footer markers (system-generated)
        $this->assertStringContainsString('No. Dokumen', $html);
        $this->assertStringContainsString('Edisi/Revisi', $html);
        $this->assertStringContainsString('Tgl. Efektif', $html);
        $this->assertStringContainsString('Halaman', $html);
        $this->assertStringContainsString('Dibuat Oleh', $html);
        $this->assertStringContainsString('Diperiksa Oleh', $html);
        $this->assertStringContainsString('Disahkan Oleh', $html);
        $this->assertStringContainsString('Nama/Pangkat', $html);
        $this->assertStringContainsString('Gifari/Penata TK I', $html);
        $this->assertStringContainsString('Jabatan', $html);
        $this->assertStringContainsString('Admin', $html);
    }

    public function test_build_watermarked_html_prefers_layout_profile_from_revision_schema_snapshot(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create([
            'name' => 'Gifari',
            'role' => 'admin',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Matrix',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => [
                'layout_profile' => 'risk_matrix',
                'risk_matrix_columns' => ['Aspek', 'Nilai', 'Kontrol'],
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'risk', 'label' => 'Risiko', 'type' => 'text', 'required' => false],
                    ],
                ],
            ],
        ]);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-DL-001',
            'title' => 'Formulir Uji',
            'clause' => 4,
            'doc_type' => 'formulir',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_version' => 1,
            'form_schema_json' => [
                'version' => 1,
                'doc_type' => 'fr',
                'layout_profile' => 'declaration',
                'declaration_header' => 'Pernyataan Uji',
                'questions' => [
                    ['id' => 'risk', 'label' => 'Risiko', 'type' => 'text', 'required' => false],
                ],
            ],
            'answers_json' => [
                'risk' => 'Rendah',
            ],
            'dibuat_oleh' => $actor->id,
        ]);

        $service = new QmhRevisionDownloadService;
        $html = $service->buildWatermarkedHtml($revision, 'SALINAN TERKENDALI');

        $this->assertStringContainsString('class="fr-minimal-header"', $html);
        $this->assertStringNotContainsString('No. Dokumen', $html);
        $this->assertStringNotContainsString('<table class="risk-matrix-table">', $html);
    }

    public function test_build_watermarked_html_rejects_non_image_custom_logo_path(): void
    {
        @mkdir(storage_path('app/public/qmh'), 0777, true);
        file_put_contents(storage_path('app/public/qmh/logo-not-image.txt'), 'plain text logo payload');

        /** @var User $actor */
        $actor = User::factory()->create([
            'name' => 'Gifari',
            'role' => 'admin',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Non Image Logo',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => [
                'layout_profile' => 'declaration',
                'logo_source' => 'custom',
                'logo_path' => 'storage/qmh/logo-not-image.txt',
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'statement', 'label' => 'Pernyataan', 'type' => 'text', 'required' => false],
                    ],
                ],
            ],
        ]);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-DL-002',
            'title' => 'Formulir Uji Logo',
            'clause' => 4,
            'doc_type' => 'formulir',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_version' => 1,
            'form_schema_json' => [
                'version' => 1,
                'doc_type' => 'fr',
                'layout_profile' => 'declaration',
                'logo_source' => 'custom',
                'logo_path' => 'storage/qmh/logo-not-image.txt',
                'questions' => [
                    ['id' => 'statement', 'label' => 'Pernyataan', 'type' => 'text', 'required' => false],
                ],
            ],
            'answers_json' => [
                'statement' => 'ok',
            ],
            'dibuat_oleh' => $actor->id,
        ]);

        $service = new QmhRevisionDownloadService;
        $html = $service->buildWatermarkedHtml($revision, 'SALINAN TERKENDALI');

        $this->assertStringContainsString('class="fr-minimal-header"', $html);
        $this->assertStringNotContainsString('No. Dokumen', $html);
        $this->assertStringNotContainsString('data:text/plain', $html);
    }
}
