<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

class QmhTemplateManagementWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_admin_with_template_manage_permission_can_access_template_management_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/templates')
            ->assertOk()
            ->assertSee('Template QMH')
            ->assertSee('Buat / Upload Template')
            ->assertSee('id="upload-template"', false)
            ->assertSee('<details', false);
    }

    public function test_admin_can_access_template_edit_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template SOP Existing',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/sop/existing.docx',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.edit', $template))
            ->assertOk()
            ->assertSee('Edit Template QMH')
            ->assertSee('Template SOP Existing')
            ->assertSee('@click="addTableRowBefore()"', false)
            ->assertSee('@click="mergeTableCells()"', false)
            ->assertSee('@click="splitTableCell()"', false);
    }

    public function test_user_without_template_manage_permission_is_redirected_from_template_management_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality/templates')
            ->assertRedirect('/dashboard');
    }

    public function test_can_upload_new_qmh_template_docx_and_activate_it(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post('/quality/templates', [
            'name' => 'Template SOP v1',
            'doc_type' => 'sop',
            'version_notes' => 'Template awal',
            'file' => UploadedFile::fake()->create(
                'template-sop-v1.docx',
                128,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ]);

        $response->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();

        $this->assertSame('Template SOP v1', $template->name);
        $this->assertSame(4, $template->clause);
        $this->assertSame('sop', $template->doc_type);
        $this->assertTrue($template->is_active);
        $this->assertNotNull($template->source_docx_path);
    }

    public function test_can_create_new_qmh_template_without_docx_and_activate_it(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template SOP HTML-only',
                'doc_type' => 'sop',
                'version_notes' => 'tanpa docx',
                'content_html' => '<h1>Judul</h1><p>Isi</p>',
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();

        $this->assertSame('Template SOP HTML-only', $template->name);
        $this->assertSame('sop', $template->doc_type);
        $this->assertTrue($template->is_active);
        $this->assertNull($template->source_docx_path);
        $this->assertSame('<h1>Judul</h1><p>Isi</p>', data_get($template->metadata, 'content_html'));
    }

    public function test_upload_template_extracts_initial_browser_content_from_docx(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template SOP with DOCX Content',
                'doc_type' => 'sop',
                'version_notes' => 'parse docx',
                'file' => $this->makeDocxUpload(),
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();
        $this->assertSame('<p>Judul SOP</p><p>Langkah 1</p>', data_get($template->metadata, 'content_html'));
    }

    public function test_upload_template_preserves_table_structure_from_docx_for_browser_editor(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template SOP with Table',
                'doc_type' => 'sop',
                'version_notes' => 'parse table',
                'file' => $this->makeDocxUploadWithTable(),
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();
        $contentHtml = (string) data_get($template->metadata, 'content_html', '');

        $this->assertStringContainsString('<table', strtolower($contentHtml));
        $this->assertStringContainsString('Kolom A', $contentHtml);
        $this->assertStringContainsString('Kolom B', $contentHtml);
    }

    public function test_upload_template_preserves_numbering_and_footer_text_from_docx(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template SOP with Numbering',
                'doc_type' => 'sop',
                'version_notes' => 'parse numbering and footer',
                'file' => $this->makeDocxUploadWithNumberingAndFooter(),
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();
        $contentHtml = (string) data_get($template->metadata, 'content_html', '');

        $this->assertStringContainsString('<p>1. TUJUAN</p>', $contentHtml);
        $this->assertStringContainsString('<p>2. RUANG LINGKUP</p>', $contentHtml);
        $this->assertMatchesRegularExpression('/<p>(?:&bull;|\x{2022})\s*IK X<\/p>/u', $contentHtml);
        $this->assertMatchesRegularExpression('/<p>(?:&bull;|\x{2022})\s*FR X<\/p>/u', $contentHtml);
        $this->assertStringContainsString('Isi Dokumen ini tidak diperkenankan', $contentHtml);
        $this->assertStringContainsString('<p>1/1</p>', $contentHtml);
    }

    public function test_upload_template_preserves_footer_alignment_and_text_style_from_docx(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template SOP with Footer Styles',
                'doc_type' => 'sop',
                'version_notes' => 'parse footer styles',
                'file' => $this->makeDocxUploadWithStyledFooter(),
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();
        $contentHtml = (string) data_get($template->metadata, 'content_html', '');

        $this->assertMatchesRegularExpression('/<p style="text-align:\s*right;?">.*1\/1.*<\/p>/', $contentHtml);
        $this->assertMatchesRegularExpression('/<p style="text-align:\s*center;?">/', $contentHtml);
        $this->assertMatchesRegularExpression('/style="color:\s*#FF0000"/i', $contentHtml);
        $this->assertStringContainsString('<em>', $contentHtml);
        $this->assertStringContainsString('Isi Dokumen ini tidak diperkenankan', $contentHtml);
    }

    public function test_can_update_template_name_doc_type_and_replace_file(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        Storage::disk('local')->put('qmh/templates/sop/original.docx', 'old-content');

        $template = QmhTemplate::query()->create([
            'name' => 'Template Lama',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/sop/original.docx',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('quality.templates.update', $template), [
                'name' => 'Template Baru',
                'doc_type' => 'ik',
                'version_notes' => 'ubah jenis dokumen',
                'file' => UploadedFile::fake()->create(
                    'template-baru.docx',
                    128,
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ),
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template->refresh();
        $this->assertSame('Template Baru', $template->name);
        $this->assertSame('ik', $template->doc_type);
        $this->assertNotSame('qmh/templates/sop/original.docx', $template->source_docx_path);
    }

    public function test_can_update_template_browser_content(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template Konten Browser',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/sop/browser.docx',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Lama</p>',
            ],
        ]);

        $this->actingAs($user)
            ->patch(route('quality.templates.update', $template), [
                'name' => 'Template Konten Browser',
                'doc_type' => 'sop',
                'content_html' => '<p>Baru dari browser</p>',
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template->refresh();
        $this->assertSame('<p>Baru dari browser</p>', data_get($template->metadata, 'content_html'));
    }

    public function test_can_update_template_form_schema_metadata_from_json_textarea(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template Schema Pertanyaan',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/sop/schema.docx',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Lama</p>',
            ],
        ]);

        $schema = [
            'version' => 1,
            'questions' => [
                [
                    'id' => 'purpose',
                    'label' => 'Tujuan',
                    'type' => 'textarea',
                    'required' => true,
                ],
            ],
        ];

        $this->actingAs($user)
            ->patch(route('quality.templates.update', $template), [
                'name' => 'Template Schema Pertanyaan',
                'doc_type' => 'sop',
                'form_schema_json' => json_encode($schema, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template->refresh();

        $this->assertSame('purpose', data_get($template->metadata, 'form_schema.questions.0.id'));
        $this->assertSame('Tujuan', data_get($template->metadata, 'form_schema.questions.0.label'));
        $this->assertTrue((bool) data_get($template->metadata, 'form_schema.questions.0.required'));
    }

    public function test_update_template_rejects_invalid_form_schema_json(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template Invalid Schema',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/invalid-schema.docx',
            'is_active' => true,
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_a', 'label' => 'A', 'type' => 'text', 'required' => false],
                ['id' => 'field_a', 'label' => 'A dupe', 'type' => 'text', 'required' => false],
            ],
        ];

        $this->actingAs($user)
            ->from(route('quality.templates.edit', $template))
            ->patch(route('quality.templates.update', $template), [
                'name' => 'Template Invalid Schema',
                'doc_type' => 'fr',
                'form_schema_json' => json_encode($schema, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('quality.templates.edit', $template));

        $this->assertTrue(session()->has('errors'));
        $this->assertNotEmpty(session('errors')->get('form_schema_json'));
    }

    public function test_activating_template_deactivates_other_active_template_in_same_doc_type(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $first = QmhTemplate::query()->create([
            'name' => 'Template SOP Klausul 4 v1',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'templates/qmh/sop-4-v1.docx',
            'is_active' => true,
        ]);

        $second = QmhTemplate::query()->create([
            'name' => 'Template SOP Klausul 8 v2',
            'clause' => 8,
            'doc_type' => 'sop',
            'version' => 2,
            'storage_disk' => 'local',
            'source_docx_path' => 'templates/qmh/sop-8-v2.docx',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->patch('/quality/templates/'.$second->id.'/activate')
            ->assertRedirect(route('quality.templates.index'));

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_template_preview_opens_in_browser_page_not_direct_download(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $path = 'qmh/templates/sop/template-preview.docx';
        Storage::disk('local')->put($path, 'dummy-docx-content');

        $template = QmhTemplate::query()->create([
            'name' => 'Template Preview SOP',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => $path,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.preview', $template))
            ->assertOk()
            ->assertSee('Preview Template QMH')
            ->assertSee('Buka File Langsung')
            ->assertDontSee('view.officeapps.live.com');
    }

    public function test_template_preview_sanitizes_html_content_to_prevent_xss(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template XSS',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>OK</p><script>alert("xss")</script><img src="https://evil.example/x">',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.preview', $template))
            ->assertOk()
            ->assertSee('OK')
            ->assertDontSee('alert("xss")', false)
            ->assertDontSee('evil.example', false);
    }

    public function test_template_preview_renders_html_preview_when_no_docx(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template Preview HTML-only',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Halo Preview</p>',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.preview', $template))
            ->assertOk()
            ->assertSee('Preview Template QMH')
            ->assertSee('Halo Preview', false)
            ->assertDontSee('view.officeapps.live.com');
    }

    public function test_signed_preview_file_endpoint_returns_docx_stream(): void
    {
        Storage::fake('local');

        $path = 'qmh/templates/sop/template-preview.docx';
        Storage::disk('local')->put($path, 'dummy-docx-content');

        $template = QmhTemplate::query()->create([
            'name' => 'Template Preview SOP',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => $path,
            'is_active' => true,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'quality.templates.preview.file',
            now()->addMinutes(5),
            ['template' => $template->id]
        );

        $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
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

        $reportPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.report'],
            [
                'display_name' => 'Lihat Laporan Quality Management Hub',
                'module' => 'qmh',
                'action' => 'report',
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

        foreach ([$viewPermission, $createPermission, $reportPermission, $templateManagePermission] as $permission) {
            RolePermission::query()->updateOrCreate([
                'role' => 'admin',
                'permission_id' => $permission->id,
            ]);
        }
    }

    private function makeDocxUpload(): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'qmh-docx-');
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .'<w:p><w:r><w:t>Judul SOP</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Langkah 1</w:t></w:r></w:p>'
            .'</w:body>'
            .'</w:document>';

        $zip->addFromString('word/document.xml', $docXml);
        $zip->close();

        return new UploadedFile(
            $tempPath,
            'template.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    private function makeDocxUploadWithTable(): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'qmh-docx-table-');
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .'<w:tbl>'
            .'<w:tr>'
            .'<w:tc><w:p><w:r><w:t>Kolom A</w:t></w:r></w:p></w:tc>'
            .'<w:tc><w:p><w:r><w:t>Kolom B</w:t></w:r></w:p></w:tc>'
            .'</w:tr>'
            .'</w:tbl>'
            .'</w:body>'
            .'</w:document>';

        $zip->addFromString('word/document.xml', $docXml);
        $zip->close();

        return new UploadedFile(
            $tempPath,
            'template-table.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    private function makeDocxUploadWithNumberingAndFooter(): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'qmh-docx-numbering-');
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<w:body>'
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>TUJUAN</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>RUANG LINGKUP</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>IK X</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>FR X</w:t></w:r></w:p>'
            .'<w:sectPr><w:footerReference w:type="default" r:id="rId8"/></w:sectPr>'
            .'</w:body>'
            .'</w:document>';

        $numberingXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl></w:abstractNum>'
            .'<w:abstractNum w:abstractNumId="2"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl></w:abstractNum>'
            .'<w:num w:numId="1"><w:abstractNumId w:val="1"/></w:num>'
            .'<w:num w:numId="2"><w:abstractNumId w:val="2"/></w:num>'
            .'</w:numbering>';

        $documentRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId8" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer2.xml"/>'
            .'</Relationships>';

        $footerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:p><w:r><w:fldChar w:fldCharType="begin"/><w:instrText xml:space="preserve">PAGE</w:instrText><w:fldChar w:fldCharType="separate"/><w:fldChar w:fldCharType="end"/></w:r><w:r><w:t>/1</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Isi Dokumen ini tidak diperkenankan untuk disalin atau digandakan tanpa persetujuan</w:t></w:r></w:p>'
            .'</w:ftr>';

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/numbering.xml', $numberingXml);
        $zip->addFromString('word/_rels/document.xml.rels', $documentRelsXml);
        $zip->addFromString('word/footer2.xml', $footerXml);
        $zip->close();

        return new UploadedFile(
            $tempPath,
            'template-numbering.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    private function makeDocxUploadWithStyledFooter(): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'qmh-docx-footer-style-');
        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<w:body>'
            .'<w:p><w:r><w:t>Konten Dokumen</w:t></w:r></w:p>'
            .'<w:sectPr><w:footerReference w:type="default" r:id="rId8"/></w:sectPr>'
            .'</w:body>'
            .'</w:document>';

        $documentRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId8" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer2.xml"/>'
            .'</Relationships>';

        $footerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:p><w:pPr><w:jc w:val="right"/></w:pPr><w:r><w:fldChar w:fldCharType="begin"/><w:instrText xml:space="preserve">PAGE</w:instrText><w:fldChar w:fldCharType="separate"/><w:fldChar w:fldCharType="end"/></w:r><w:r><w:t>/1</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:i/><w:color w:val="FF0000"/></w:rPr><w:t>Isi Dokumen ini tidak diperkenankan</w:t></w:r></w:p>'
            .'</w:ftr>';

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $documentRelsXml);
        $zip->addFromString('word/footer2.xml', $footerXml);
        $zip->close();

        return new UploadedFile(
            $tempPath,
            'template-footer-style.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }
}
