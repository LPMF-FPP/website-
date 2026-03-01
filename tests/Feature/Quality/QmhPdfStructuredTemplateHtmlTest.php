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
        $this->assertStringContainsString('page-number', $html);
        $this->assertStringNotContainsString('DARI -', $html);
        $this->assertStringContainsString('Halaman <span class="page-number"></span>/-', $html);
        $this->assertStringContainsString('SOP UJI PDF', $html);
        $this->assertStringNotContainsString('[SOP UJI PDF]', $html);

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

    public function test_pdf_template_uses_resolved_page_count_when_provided(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-PDF-PAGE-001',
            'title' => 'SOP Uji Halaman',
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
                'purpose' => '<p>Tujuan</p>',
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
            'watermarkText' => 'DRAFT',
            'resolvedPageCount' => 2,
        ])->render();

        $this->assertStringNotContainsString('DARI 2', $html);
        $this->assertStringContainsString('Halaman <span class="page-number"></span>/2', $html);
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

    public function test_pdf_template_renders_non_table_layout_for_fr_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-PDF-DECL-001',
            'title' => 'Formulir Declaration',
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
                'statement' => 'Kami menjamin ketidakberpihakan.',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'layout_profile' => 'non_table',
            'declaration_header' => 'Pernyataan Ketidakberpihakan',
            'questions' => [
                ['id' => 'statement', 'label' => 'Pernyataan', 'type' => 'textarea', 'required' => true],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
            'layoutProfile' => 'non_table',
            'layoutConfig' => [
                'layout_profile' => 'non_table',
                'shell_mode' => 'full',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => true,
                'declaration_header' => 'Pernyataan Ketidakberpihakan',
            ],
        ])->render();

        $this->assertStringContainsString('form-table', $html);
        $this->assertStringContainsString('PERNYATAAN', $html);
        $this->assertStringContainsString('Kami menjamin ketidakberpihakan.', $html);
        $this->assertStringContainsString('fr-minimal-header', $html);
        $this->assertStringContainsString('fr-minimal-footer', $html);
        $this->assertStringNotContainsString('No. Dokumen', $html);
        $this->assertStringNotContainsString('Dibuat Oleh:', $html);
    }

    public function test_pdf_template_renders_risk_matrix_layout_for_fr_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-PDF-RISK-001',
            'title' => 'Formulir Risk Matrix',
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
                'risk_level' => 'Sedang',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'layout_profile' => 'risk_matrix',
            'risk_matrix_columns' => ['Aspek', 'Nilai', 'Kontrol'],
            'questions' => [
                ['id' => 'risk_level', 'label' => 'Level Risiko', 'type' => 'text', 'required' => false, 'help' => 'Gunakan skala 1-5'],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
            'layoutProfile' => 'risk_matrix',
            'layoutConfig' => [
                'layout_profile' => 'risk_matrix',
                'risk_matrix_columns' => ['Aspek', 'Nilai', 'Kontrol'],
            ],
        ])->render();

        $this->assertStringContainsString('risk-matrix-table', $html);
        $this->assertStringContainsString('<th>ASPEK</th>', $html);
        $this->assertStringContainsString('<th>KONTROL</th>', $html);
        $this->assertStringContainsString('No. Dokumen', $html);
        $this->assertStringContainsString('Dibuat Oleh:', $html);
    }

    public function test_pdf_template_renders_structured_form_with_fr_shell_markers(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-PDF-STRUCT-001',
            'title' => 'Formulir Structured Form',
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
                'field_a' => 'Nilai A',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'layout_profile' => 'structured_form',
            'questions' => [
                ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => false],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
            'layoutProfile' => 'structured_form',
            'layoutConfig' => [
                'layout_profile' => 'structured_form',
            ],
        ])->render();

        $this->assertStringContainsString('form-table', $html);
        $this->assertStringContainsString('fr-minimal-header', $html);
        $this->assertStringContainsString('fr-minimal-footer', $html);
        $this->assertStringNotContainsString('No. Dokumen', $html);
        $this->assertStringNotContainsString('Dibuat Oleh:', $html);
    }

    public function test_pdf_template_renders_fr_non_table_with_minimal_header_and_footer(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-PDF-MIN-001',
            'title' => 'Formulir Minimal Shell',
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
                'field_a' => 'Nilai A',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'layout_profile' => 'structured_form',
            'questions' => [
                ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => false],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
            'layoutProfile' => 'structured_form',
            'layoutConfig' => [
                'layout_profile' => 'structured_form',
                'shell_mode' => 'full',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => true,
            ],
        ])->render();

        $this->assertStringContainsString('fr-minimal-header', $html);
        $this->assertStringContainsString('fr-minimal-footer', $html);
        $this->assertStringNotContainsString('No. Dokumen', $html);
        $this->assertStringNotContainsString('Dibuat Oleh:', $html);
    }

    public function test_pdf_template_renders_ik_body_without_table_layout(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-IK-PDF-NONTABLE-001',
            'title' => 'IK Naratif',
            'clause' => 4,
            'doc_type' => 'ik',
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
                'purpose' => '<p>Tujuan IK</p>',
                'scope' => '<p>Ruang lingkup IK</p>',
                'instructions' => '<p>Langkah kerja IK</p>',
            ],
        ]);

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => ['version' => 1, 'doc_type' => 'ik', 'questions' => []],
            'answers' => $revision->answers_json,
            'watermarkText' => 'DRAFT',
        ])->render();

        $this->assertStringContainsString('1. TUJUAN', $html);
        $this->assertStringContainsString('5. INSTRUKSI KERJA', $html);
        $this->assertStringNotContainsString('<table class="doc-header" style="margin-top: 6px">', $html);
    }
}
