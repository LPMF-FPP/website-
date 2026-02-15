<?php

namespace Tests\Feature\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\User;
use App\Services\Quality\QmhRevisionDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhPdfStructuredTemplateHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_template_contains_structured_header_footer_markers(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-PDF-001',
            'title' => 'SOP Uji PDF',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 2,
            'revision_number' => 4,
            'version_label' => 'E2-R4',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'effective_date' => '2026-02-14',
            'answers_json' => [
                'purpose' => 'Tujuan dokumen.',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'sop',
            'questions' => [
                ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'CONTROLLED COPY',
        ])->render();

        $this->assertStringContainsString('No. Dokumen', $html);
        $this->assertStringContainsString('Edisi/Revisi', $html);
        $this->assertStringContainsString('Tgl. Efektif', $html);
        $this->assertStringContainsString('Halaman', $html);
        $this->assertStringContainsString('{PAGE_NUM} DARI {PAGE_COUNT}', $html);
        $this->assertStringContainsString('{PAGE_NUM}/{PAGE_COUNT}', $html);

        $this->assertStringContainsString('Dibuat Oleh:', $html);
        $this->assertStringContainsString('Diperiksa Oleh:', $html);
        $this->assertStringContainsString('Disahkan Oleh:', $html);
    }

    public function test_pdf_template_renders_rich_text_answers_and_ordered_lists(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-PDF-HTML-001',
            'title' => 'SOP Uji Rich Text',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'answers_json' => [
                'purpose' => '<p><strong>Tujuan</strong> <em>dokumen</em></p>',
                'definitions' => '<ol><li><p>Definisi 1</p></li><li><p>Definisi 2</p></li></ol>',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'sop',
            'questions' => [
                ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                ['id' => 'definitions', 'label' => 'Definisi', 'type' => 'list', 'required' => false],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'UNCONTROLLED COPY',
        ])->render();

        $this->assertStringContainsString('<strong>Tujuan</strong>', $html);
        $this->assertStringContainsString('<em>dokumen</em>', $html);
        $this->assertStringContainsString('<ol', $html);
        $this->assertStringContainsString('Definisi 2', $html);
    }

    public function test_pdf_template_renders_formulir_schema_as_table(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-PDF-001',
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
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'answers_json' => [],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => false],
                ['id' => 'field_b', 'label' => 'Kolom B', 'type' => 'textarea', 'required' => false],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
        ])->render();

        $this->assertStringContainsString('form-table', $html);
        $this->assertStringContainsString('KOLOM A', $html);
        $this->assertStringContainsString('KOLOM B', $html);
    }

    public function test_pdf_generation_prefers_revision_schema_snapshot_over_template_schema(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Base PDF',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => false],
                    ],
                ],
            ],
        ]);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-PDF-SNAPSHOT-001',
            'title' => 'Formulir Snapshot PDF',
            'clause' => 4,
            'doc_type' => 'formulir',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $overrideSchema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_b', 'label' => 'Kolom B', 'type' => 'text', 'required' => false],
            ],
        ];

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_version' => $template->version,
            'form_schema_json' => $overrideSchema,
            'answers_json' => [
                'field_b' => 'OK',
            ],
        ]);

        $service = new QmhRevisionDownloadService;
        $html = $service->buildWatermarkedHtml($revision->fresh(), 'DRAFT');

        $this->assertStringContainsString('KOLOM B', $html);
        $this->assertStringNotContainsString('KOLOM A', $html);
    }

    public function test_pdf_template_renders_fr_doc_type_as_formulir_table(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        // Note: DB constraint for qmh_documents.doc_type may not allow 'fr'.
        // Preview PDF flow can still pass doc_type='fr' without persisting document.
        $document = new QmhDocument([
            'doc_code' => 'QMH-FR-PDF-DT-001',
            'title' => 'FR Doc Type',
            'clause' => 4,
            'doc_type' => 'fr',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = new QmhDocumentRevision([
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'effective_date' => '2026-02-15',
            'answers_json' => [
                'agree' => true,
            ],
        ]);

        $revision->setRelation('document', $document);
        $revision->setRelation('createdBy', $user);
        $revision->setRelation('reviewedBy', null);
        $revision->setRelation('approvedBy', null);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'agree', 'label' => 'Konfirmasi', 'type' => 'checkbox', 'required' => false],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision,
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
        ])->render();

        $this->assertStringContainsString('form-table', $html);
        $this->assertStringContainsString('YA', $html);
    }

    public function test_pdf_template_renders_formulir_v1_field_types_readably(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-PDF-V1-001',
            'title' => 'Formulir V1',
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
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'answers_json' => [
                'agree' => true,
                'status' => 'ok',
                'test_date' => '2026-02-15',
                'qty' => '1.50',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'sec_general', 'label' => 'Umum', 'type' => 'section', 'required' => false],
                ['id' => 'agree', 'label' => 'Konfirmasi', 'type' => 'checkbox', 'required' => false],
                ['id' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => false, 'options' => [
                    ['value' => 'ok', 'label' => 'Sesuai'],
                    ['value' => 'nok', 'label' => 'Tidak Sesuai'],
                ]],
                ['id' => 'test_date', 'label' => 'Tanggal Uji', 'type' => 'date', 'required' => false],
                ['id' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'required' => false],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
        ])->render();

        $this->assertStringContainsString('UMUM', $html);
        $this->assertStringContainsString('YA', $html);
        $this->assertStringContainsString('Sesuai', $html);
        $this->assertStringContainsString('2026-02-15', $html);
        $this->assertStringContainsString('1.50', $html);
    }
}
