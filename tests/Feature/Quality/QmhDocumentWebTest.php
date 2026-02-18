<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QmhDocumentWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->createQmhPermissions();
    }

    public function test_admin_can_access_quality_pages(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality')
            ->assertOk()
            ->assertViewIs('quality.dashboard');

        $this->actingAs($user)
            ->get('/quality/documents')
            ->assertOk()
            ->assertViewIs('quality.index');

        $this->actingAs($user)
            ->get('/quality/documents/create')
            ->assertOk()
            ->assertViewIs('quality.create')
            ->assertSee('Buat Dokumen')
            ->assertSee('Dasar Dokumen')
            ->assertSee('Pilih Template')
            ->assertDontSee('Editor Konten Awal')
            ->assertDontSee('2. Preview')
            ->assertDontSee('Lanjut ke Preview')
            ->assertSee('Kembali');
    }

    public function test_quality_pages_render_breadcrumbs_and_qmh_subnav_with_active_state(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $landing = $this->actingAs($user)->get('/quality');
        $landing->assertOk();
        $this->assertBreadcrumbLabels($landing->getContent(), ['Dashboard QMH']);
        $this->assertQmhSubnavActiveLabel($landing->getContent(), 'Ringkasan');

        $documents = $this->actingAs($user)->get('/quality/documents');
        $documents->assertOk();
        $this->assertBreadcrumbLabels($documents->getContent(), ['Dashboard QMH', 'Dokumen']);
        $this->assertQmhSubnavActiveLabel($documents->getContent(), 'Dokumen');

        $create = $this->actingAs($user)->get('/quality/documents/create');
        $create->assertOk();
        $this->assertBreadcrumbLabels($create->getContent(), ['Dashboard QMH', 'Buat Dokumen']);
        $this->assertQmhSubnavActiveLabel($create->getContent(), 'Buat Dokumen');

        $templates = $this->actingAs($user)->get('/quality/templates');
        $templates->assertOk();
        $this->assertBreadcrumbLabels($templates->getContent(), ['Dashboard QMH', 'Template QMH']);
        $this->assertQmhSubnavActiveLabel($templates->getContent(), 'Template');

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-CR-001',
            'title' => 'SOP CR',
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
            'content_html' => '<p>Konten</p>',
        ]);
        $document->update(['current_revision_id' => $revision->id]);

        $show = $this->actingAs($user)->get('/quality/documents/'.$document->id);
        $show->assertOk();
        $this->assertBreadcrumbLabels($show->getContent(), ['Dashboard QMH', 'Dokumen', 'QMH-SOP-CR-001']);
        $this->assertQmhSubnavActiveLabel($show->getContent(), 'Dokumen');

        $edit = $this->actingAs($user)->get('/quality/documents/'.$document->id.'/edit');
        $edit->assertOk();
        $this->assertBreadcrumbLabels($edit->getContent(), ['Dashboard QMH', 'Dokumen', 'Editor']);
        $this->assertQmhSubnavActiveLabel($edit->getContent(), 'Dokumen');
    }

    public function test_reports_page_renders_breadcrumbs_and_subnav_when_user_has_permission(): void
    {
        $this->grantPermissionToRole('admin', 'qmh.report', 'Laporan Quality Management Hub', 'qmh', 'report');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $reports = $this->actingAs($user)->get('/quality/reports');

        $reports->assertOk();
        $this->assertBreadcrumbLabels($reports->getContent(), ['Dashboard QMH', 'Laporan QMH']);
        $this->assertQmhSubnavActiveLabel($reports->getContent(), 'Laporan');
    }

    public function test_qmh_primary_actions_use_clinical_primary_palette(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $landing = $this->actingAs($user)->get('/quality');
        $landing->assertOk();
        $this->assertElementWithTextHasClass($landing->getContent(), 'a', 'Buat Dokumen', 'bg-primary-600');

        $documents = $this->actingAs($user)->get('/quality/documents');
        $documents->assertOk();
        $this->assertElementWithTextHasClass($documents->getContent(), 'a', 'Buat Dokumen', 'bg-primary-600');

        $templates = $this->actingAs($user)->get('/quality/templates');
        $templates->assertOk();
        $this->assertElementWithTextHasClass($templates->getContent(), 'a', 'Buat Template', 'bg-primary-600');
    }

    public function test_create_page_is_guided_stepper_flow_with_clarified_doc_type_microcopy(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/quality/documents/create');

        $response->assertOk();
        $response->assertSee('Alur Pembuatan Dokumen');
        $response->assertSee('1. Dasar Dokumen');
        $response->assertSee('2. Metadata');
        $response->assertSee('3. Isi & Penanda Tangan', false);
        $response->assertSee('4. Review');
        $response->assertSee('Instruksi Kerja (IK)');
        $response->assertSee('Formulir (FR)');
    }

    public function test_user_without_qmh_permission_is_redirected_from_quality_pages(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality')
            ->assertRedirect('/dashboard');
    }

    public function test_create_page_uses_global_qmh_create_factory(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/documents/create')
            ->assertOk()
            ->assertSee('x-data="window.qmhCreatePage({', false);
    }

    public function test_can_store_document_from_web_form_and_redirects_to_editor(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $template = $this->createTemplate(5, 'sop');

        $response = $this->actingAs($user)
            ->post('/quality/documents', [
                'doc_code' => 'QMH-WEB-001',
                'title' => 'Dokumen dari Form Web',
                'clause' => 5,
                'doc_type' => 'sop',
                'template_id' => $template->id,
                'change_summary' => 'create from web',
                'dibuat_oleh' => $user->id,
            ]);

        $createdDocument = QmhDocument::query()
            ->where('doc_code', 'QMH-WEB-001')
            ->firstOrFail();

        $response->assertRedirect(route('quality.documents.edit', $createdDocument));

        $this->assertDatabaseHas('qmh_documents', [
            'doc_code' => 'QMH-WEB-001',
            'title' => 'Dokumen dari Form Web',
            'clause' => 5,
            'doc_type' => 'sop',
        ]);
    }

    public function test_store_non_formulir_ignores_empty_form_schema_json(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $template = $this->createTemplate(5, 'sop');

        $response = $this->actingAs($user)
            ->post('/quality/documents', [
                'doc_code' => 'QMH-WEB-002',
                'title' => 'Dokumen SOP dengan schema kosong',
                'clause' => 5,
                'doc_type' => 'sop',
                'template_id' => $template->id,
                'change_summary' => 'store with empty schema field',
                'dibuat_oleh' => $user->id,
                'form_schema_json' => '',
            ]);

        $createdDocument = QmhDocument::query()
            ->where('doc_code', 'QMH-WEB-002')
            ->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('quality.documents.edit', $createdDocument));

        $this->assertDatabaseHas('qmh_documents', [
            'doc_code' => 'QMH-WEB-002',
            'title' => 'Dokumen SOP dengan schema kosong',
            'doc_type' => 'sop',
        ]);
    }

    public function test_store_fr_v2_requires_source_pdf_when_gate_enabled(): void
    {
        config()->set('quality.fr_v2.enabled', true);
        config()->set('quality.fr_v2.create_enabled', true);

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-PARENT-001',
            'title' => 'SOP Parent FR',
            'clause' => 5,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->from('/quality/documents/create')
            ->post('/quality/documents', [
                'doc_code' => 'QMH-FR-V2-001',
                'title' => 'Formulir FR-v2 Tanpa PDF',
                'clause' => 5,
                'doc_type' => 'fr',
                'fr_v2_structure_mode' => 'non_table',
                'parent_sop_id' => $parentSop->id,
                'dibuat_oleh' => $user->id,
            ]);

        $response->assertRedirect('/quality/documents/create');
        $response->assertSessionHasErrors(['source_pdf_file']);

        $this->assertDatabaseMissing('qmh_documents', [
            'doc_code' => 'QMH-FR-V2-001',
        ]);
    }

    public function test_store_fr_v2_uses_builtin_layout_from_structure_mode_without_template_id(): void
    {
        config()->set('quality.fr_v2.enabled', true);
        config()->set('quality.fr_v2.create_enabled', true);

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-PARENT-002',
            'title' => 'SOP Parent FR Auto',
            'clause' => 5,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $sourcePdf = UploadedFile::fake()->createWithContent('source.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n2 0 obj\n<< /Type /Page >>\nendobj\n");

        $response = $this->actingAs($user)
            ->post('/quality/documents', [
                'doc_code' => 'QMH-FR-V2-002',
                'title' => 'Formulir FR-v2 Auto Template',
                'clause' => 5,
                'doc_type' => 'fr',
                'fr_v2_structure_mode' => 'non_table',
                'parent_sop_id' => $parentSop->id,
                'dibuat_oleh' => $user->id,
                'source_pdf_file' => $sourcePdf,
            ]);

        $createdDocument = QmhDocument::query()
            ->where('doc_code', 'QMH-FR-V2-002')
            ->firstOrFail();

        $response->assertRedirect(route('quality.documents.edit', $createdDocument));

        $revision = QmhDocumentRevision::query()
            ->where('document_id', $createdDocument->id)
            ->firstOrFail();

        $this->assertNull($revision->template_id);
        $this->assertIsArray($revision->form_schema_json);
        $this->assertSame('structured_form', $revision->form_schema_json['layout_profile'] ?? null);
        $this->assertSame('full', $revision->form_schema_json['shell_mode'] ?? null);
        $this->assertSame('portrait', $revision->form_schema_json['orientation_policy'] ?? null);
        $this->assertSame(true, $revision->form_schema_json['show_signoff_footer'] ?? null);
        $this->assertNotNull($revision->source_pdf_path);
        $this->assertNotNull($revision->source_pdf_sha256);
    }

    public function test_web_index_supports_search_and_filter_query_params(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $docOne = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-100',
            'title' => 'SOP Umum',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revisionOne = QmhDocumentRevision::query()->create([
            'document_id' => $docOne->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $docOne->update(['current_revision_id' => $revisionOne->id]);

        $docTwo = QmhDocument::query()->create([
            'doc_code' => 'QMH-IK-200',
            'title' => 'IK Kalibrasi Neraca',
            'clause' => 6,
            'doc_type' => 'ik',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revisionTwo = QmhDocumentRevision::query()->create([
            'document_id' => $docTwo->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $docTwo->update(['current_revision_id' => $revisionTwo->id]);

        $response = $this->actingAs($user)
            ->get('/quality/documents?search=QMH&clause=6&doc_type=ik&status=draft&edition_number=1&revision_number=0');

        $response->assertOk();
        $response->assertSee('Dashboard QMH');
        $response->assertSee('Lihat');
        $response->assertSee('QMH-IK-200');
        $response->assertDontSee('QMH-SOP-100');
    }

    public function test_quality_landing_shows_summary_cards(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-300',
            'title' => 'SOP Validasi',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $currentRevision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'version_label' => 'E1-R2',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $document->update(['current_revision_id' => $currentRevision->id]);

        QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 1,
            'version_label' => 'E1-R1',
            'status' => 'obsolete',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'obsolete_at' => now()->subDay(),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $currentRevision->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'copy_type' => 'controlled',
            'downloaded_by' => $user->id,
            'downloaded_at' => now(),
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('a', 64),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $currentRevision->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $user->id,
            'downloaded_at' => now(),
            'reason' => 'referensi',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('b', 64),
        ]);

        $response = $this->actingAs($user)->get('/quality');

        $response->assertOk();
        $response->assertSee('Total Dokumen');
        $response->assertSee('Dokumen Terbit');
        $response->assertSee('Dokumen Dalam Tinjauan');
        $response->assertSee('Revisi Kedaluwarsa');
        $response->assertSee('Unduhan Terkendali');
        $response->assertSee('Unduhan Tidak Terkendali');
        $response->assertSee('Semua Klausul');
        $response->assertSee('Semua Jenis');
    }

    public function test_user_with_qmh_view_permission_can_access_document_show_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-400',
            'title' => 'SOP Show Page',
            'clause' => 5,
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
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id)
            ->assertOk()
            ->assertViewIs('quality.show')
            ->assertSee('QMH-SOP-400')
            ->assertSee('function qmhShowPage(config)', false)
            ->assertSee('Konten')
            ->assertSee('Riwayat Revisi')
            ->assertSee('Riwayat Unduhan');
    }

    public function test_user_with_qmh_create_permission_can_access_document_edit_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-401',
            'title' => 'SOP Edit Page',
            'clause' => 6,
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
            'content_html' => '<p>Konten awal</p>',
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id.'/edit')
            ->assertOk()
            ->assertViewIs('quality.edit')
            ->assertSee('Editor Dokumen QMH')
            ->assertSee('Bullets')
            ->assertSee('function qmhEditPage(config)', false)
            ->assertSee('Simpan');
    }

    public function test_admin_can_delete_draft_document_via_destroy_route(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-DELETE-01',
            'title' => 'SOP Delete Draft',
            'clause' => 6,
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
            'content_html' => '<p>Konten awal</p>',
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->delete('/quality/documents/'.$document->id)
            ->assertRedirect('/quality/documents');

        $this->assertDatabaseMissing('qmh_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('qmh_document_revisions', ['id' => $revision->id]);
    }

    public function test_edit_page_workspace_exposes_right_rail_actions_and_modal_handlers(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-402-EDIT',
            'title' => 'SOP Edit Workspace',
            'clause' => 6,
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
            'content_html' => '<p>Konten awal</p>',
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id.'/edit')
            ->assertOk()
            ->assertViewIs('quality.edit')
            ->assertSee('Aksi')
            ->assertSee('Checklist')
            ->assertSee('Preview')
            ->assertSee('Simpan Draft')
            ->assertSee('Submit untuk Review')
            ->assertSee('Buka Preview')
            ->assertSee('openSubmitModal() {', false)
            ->assertSee('async submitForReview()', false)
            ->assertSee('openPreviewModal() {', false);
    }

    public function test_edit_page_displays_preview_gate_guidance_before_submit(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-403-PREVIEW-GATE',
            'title' => 'SOP Preview Gate',
            'clause' => 6,
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
            'content_html' => '<p>Konten awal</p>',
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id.'/edit')
            ->assertOk()
            ->assertViewIs('quality.edit')
            ->assertSee('Belum cek preview. Buka preview sebelum submit.')
            ->assertSee('Buka preview dokumen sebelum submit.', false)
            ->assertSee('Buka preview sekarang');
    }

    public function test_edit_page_registers_structured_rich_text_helpers_for_non_formulir(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-HELPER-001',
            'title' => 'SOP Helper Registration',
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
                'purpose' => '<?xml encoding="utf-8" ?><p>Tujuan SOP</p>',
                'scope' => '<?xml encoding="utf-8" ?><p>Ruang Lingkup SOP</p>',
                'definitions' => '<?xml encoding="utf-8" ?><ul><li><p>Definisi A</p></li></ul>',
                'procedure' => '<?xml encoding="utf-8" ?><p>Langkah kerja A</p>',
            ],
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id.'/edit')
            ->assertOk()
            ->assertSee('answerEditorInitialValue(qid) {', false)
            ->assertSee('onRichTextAnswerChange(qid, html) {', false)
            ->assertSee('onRichTextListAnswerChange(qid, html) {', false)
            ->assertSee('stripXmlDeclaration(value) {', false);
    }

    public function test_detail_page_shows_disabled_reason_for_submit_when_not_revision_owner(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-402',
            'title' => 'SOP Disabled Reason',
            'clause' => 6,
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
            'dibuat_oleh' => $owner->id,
            'content_html' => '<p>Konten awal</p>',
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $response = $this->actingAs($other)
            ->get('/quality/documents/'.$document->id)
            ->assertOk()
            ->assertSee('Submit untuk Review')
            ->assertSee('Hanya pembuat revisi yang dapat submit.');

        $this->assertSame(1, substr_count($response->getContent(), 'Hanya pembuat revisi yang dapat submit.'));
    }

    public function test_create_page_shows_template_management_link_when_no_template_available(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/documents/create')
            ->assertOk()
            ->assertSee('templateManageUrl')
            ->assertSee('Tambah template di');
    }

    public function test_create_page_uses_tiptap_for_question_editor_windows(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/documents/create')
            ->assertOk()
            ->assertSee('qmh-editor-surface qmh-editor-surface--compact', false)
            ->assertDontSee('editor_hidden_unused', false)
            ->assertSee('@click="toggleBold()"', false);
    }

    public function test_create_page_hides_schema_builder_for_non_template_manager_role(): void
    {
        $this->grantPermissionToRole('qmh_operator', 'qmh.view', 'Lihat Quality Management Hub', 'qmh', 'view');
        $this->grantPermissionToRole('qmh_operator', 'qmh.create', 'Buat Dokumen Quality Management Hub', 'qmh', 'create');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'qmh_operator']);

        $this->actingAs($user)
            ->get('/quality/documents/create')
            ->assertOk()
            ->assertSee('Format pertanyaan dikelola dari menu Template')
            ->assertDontSee('qmhFormBuilder({', false)
            ->assertDontSee('+ Pertanyaan');
    }

    public function test_create_page_hides_technical_schema_controls_even_for_admin(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/documents/create')
            ->assertOk()
            ->assertSee('Format pertanyaan dikelola dari menu Template')
            ->assertDontSee('Schema Pertanyaan (JSON)')
            ->assertDontSee('qmhFormBuilder({', false);
    }

    private function createQmhPermissions(): void
    {
        $viewPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.view'],
            [
                'display_name' => 'Lihat Quality Management Hub',
                'module' => 'qmh',
                'action' => 'view',
            ]
        );

        $createPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.create'],
            [
                'display_name' => 'Buat Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'create',
            ]
        );

        $templateManagePermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.template.manage'],
            [
                'display_name' => 'Kelola Template Quality Management Hub',
                'module' => 'qmh',
                'action' => 'template-manage',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $viewPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $templateManagePermission->id,
        ]);
    }

    private function grantPermissionToRole(
        string $role,
        string $permissionName,
        string $displayName,
        string $module,
        string $action
    ): void {
        $permission = Permission::query()->updateOrCreate(
            ['name' => $permissionName],
            [
                'display_name' => $displayName,
                'module' => $module,
                'action' => $action,
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => $role,
            'permission_id' => $permission->id,
        ]);
    }

    private function assertBreadcrumbLabels(string $html, array $expectedLabels): void
    {
        $xpath = $this->xpathFromHtml($html);

        $nav = $xpath->query("//nav[@aria-label='Breadcrumb']");
        $this->assertGreaterThan(0, $nav?->length ?? 0, 'Breadcrumb nav is missing.');

        $nodes = $xpath->query("//nav[@aria-label='Breadcrumb']//ol/li");
        $labels = [];

        if ($nodes) {
            foreach ($nodes as $li) {
                $a = $xpath->query('.//a', $li);
                if ($a && $a->length > 0) {
                    $labels[] = trim((string) $a->item(0)?->nodeValue);

                    continue;
                }

                $span = $xpath->query(".//span[@aria-current='page']", $li);
                if ($span && $span->length > 0) {
                    $labels[] = trim((string) $span->item(0)?->nodeValue);

                    continue;
                }

                $labels[] = trim((string) $li->nodeValue);
            }
        }

        $this->assertSame($expectedLabels, $labels);
    }

    private function assertQmhSubnavActiveLabel(string $html, string $expectedActiveLabel): void
    {
        $xpath = $this->xpathFromHtml($html);

        $nav = $xpath->query("//nav[@aria-label='Navigasi QMH']");
        $this->assertGreaterThan(0, $nav?->length ?? 0, 'QMH subnav is missing.');

        $active = $xpath->query("//nav[@aria-label='Navigasi QMH']//a[@aria-current='page']");
        $this->assertSame(1, $active?->length ?? 0, 'QMH subnav active link not found or not unique.');

        $text = $active?->item(0)?->nodeValue;
        $this->assertSame($expectedActiveLabel, trim((string) $text));
    }

    private function xpathFromHtml(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);

        $dom = new \DOMDocument;
        $dom->loadHTML($html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($dom);
    }

    private function assertElementWithTextHasClass(string $html, string $tag, string $text, string $expectedClassFragment): void
    {
        $xpath = $this->xpathFromHtml($html);
        $query = sprintf("//%s[normalize-space()='%s']", $tag, str_replace("'", '"', $text));
        $nodes = $xpath->query($query);

        $this->assertGreaterThan(0, $nodes?->length ?? 0, sprintf('Element <%s> with text "%s" not found.', $tag, $text));

        $class = (string) ($nodes?->item(0)?->attributes?->getNamedItem('class')?->nodeValue ?? '');
        $this->assertStringContainsString($expectedClassFragment, $class, sprintf('Expected class fragment "%s" missing on <%s> "%s".', $expectedClassFragment, $tag, $text));
    }

    private function createTemplate(int $clause, string $docType): QmhTemplate
    {
        return QmhTemplate::query()->create([
            'name' => sprintf('Template %s klausul %d', strtoupper($docType), $clause),
            'clause' => $clause,
            'doc_type' => $docType,
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => sprintf('templates/qmh/%s-%d.docx', $docType, $clause),
            'is_active' => true,
        ]);
    }

    public function test_show_page_sanitizes_rich_html_content_to_prevent_xss(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-XSS-001',
            'title' => 'SOP XSS',
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
            'content_html' => '<p>OK</p><script>alert("xss")</script>',
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id)
            ->assertOk()
            ->assertSee('OK')
            ->assertDontSee('alert("xss")', false);
    }

    public function test_show_page_prefers_structured_answers_for_sop_when_available(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-STR-001',
            'title' => 'SOP Structured',
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
            'content_html' => '<p>KONTEN TEMPLATE LAMA</p>',
            'answers_json' => [
                'purpose' => '<p>Tujuan Terbaru 4.1</p>',
                'scope' => '<p>Ruang Lingkup Terbaru 4.1</p>',
                'procedure' => '<p>Prosedur langkah A</p>',
            ],
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id)
            ->assertOk()
            ->assertSee('Tujuan Terbaru 4.1')
            ->assertSee('Ruang Lingkup Terbaru 4.1')
            ->assertSee('Prosedur langkah A')
            ->assertDontSee('KONTEN TEMPLATE LAMA');
    }
}
