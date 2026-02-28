<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
            ->assertSee('Buat Template')
            ->assertSee('Catatan Versioning')
            ->assertSee('Aksi Cepat')
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
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Isi awal</p>',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.edit', $template))
            ->assertOk()
            ->assertSee('Edit Template QMH')
            ->assertSee('Template SOP Existing');
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

    public function test_can_create_new_qmh_template_and_activate_it(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template SOP HTML-only',
                'clause' => 4,
                'doc_type' => 'sop',
                'version_notes' => 'editor-first',
                'content_html' => '<h1>Judul</h1><p>Isi</p>',
            ])
            ->assertRedirect(route('quality.templates.index'))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Template QMH') && str_contains($message, 'berhasil'));

        $template = QmhTemplate::query()->firstOrFail();

        $this->assertSame('Template SOP HTML-only', $template->name);
        $this->assertSame('sop', $template->doc_type);
        $this->assertTrue($template->is_active);
        $this->assertSame('<h1>Judul</h1><p>Isi</p>', data_get($template->metadata, 'content_html'));
    }

    public function test_can_create_fr_template_with_layout_profile_and_logo_configuration(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template FR Risk Matrix',
                'clause' => 4,
                'doc_type' => 'fr',
                'content_html' => '<p>Form FR</p>',
                'layout_profile' => 'risk_matrix',
                'logo_source' => 'custom',
                'logo_path' => 'images/logo-pusdokkes-polri.png',
                'declaration_header' => 'Header Pernyataan',
                'risk_matrix_columns_csv' => 'Aspek, Nilai, Kontrol',
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();
        $this->assertSame('risk_matrix', data_get($template->metadata, 'layout_profile'));
        $this->assertSame('custom', data_get($template->metadata, 'logo_source'));
        $this->assertSame('images/logo-pusdokkes-polri.png', data_get($template->metadata, 'logo_path'));
        $this->assertNull(data_get($template->metadata, 'declaration_header'));
        $this->assertSame('Kontrol', data_get($template->metadata, 'risk_matrix_columns.2'));
    }

    public function test_create_fr_template_defaults_layout_profile_to_structured_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template FR Structured Default',
                'clause' => 4,
                'doc_type' => 'fr',
                'content_html' => '<p>Form FR default</p>',
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();

        $this->assertSame('structured_form', data_get($template->metadata, 'layout_profile'));
        $this->assertNull(data_get($template->metadata, 'declaration_header'));
        $this->assertNull(data_get($template->metadata, 'risk_matrix_columns'));
    }

    public function test_create_fr_template_normalizes_invalid_layout_profile_to_default(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/templates', [
                'name' => 'Template FR Invalid Profile',
                'clause' => 4,
                'doc_type' => 'fr',
                'content_html' => '<p>Invalid profile</p>',
                'layout_profile' => 'invalid_profile',
            ])
            ->assertRedirect(route('quality.templates.index'));

        $template = QmhTemplate::query()->firstOrFail();

        $this->assertSame('structured_form', data_get($template->metadata, 'layout_profile'));
        $this->assertSame('full', data_get($template->metadata, 'shell_mode'));
        $this->assertSame('portrait', data_get($template->metadata, 'orientation_policy'));
        $this->assertTrue((bool) data_get($template->metadata, 'show_signoff_footer'));
    }

    public function test_update_template_publishes_new_immutable_version(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template Lama',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Lama</p>',
            ],
        ]);

        $this->actingAs($user)
            ->patch(route('quality.templates.update', $template), [
                'name' => 'Template Baru',
                'clause' => 5,
                'doc_type' => 'ik',
                'version_notes' => 'ubah jenis dokumen',
                'content_html' => '<p>Baru dari browser</p>',
            ])
            ->assertRedirect(route('quality.templates.index'))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'versi baru') && str_contains($message, 'Template QMH'));

        $template->refresh();
        $this->assertFalse($template->is_active);

        $published = QmhTemplate::query()
            ->where('name', 'Template Baru')
            ->where('doc_type', 'ik')
            ->where('clause', 5)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($published);
        $this->assertTrue((bool) $published->is_active);
        $this->assertSame('<p>Baru dari browser</p>', data_get($published?->metadata, 'content_html'));
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
                'clause' => 4,
                'doc_type' => 'sop',
                'form_schema_json' => json_encode($schema, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('quality.templates.index'));

        $published = QmhTemplate::query()
            ->where('doc_type', 'sop')
            ->where('clause', 4)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame('purpose', data_get($published->metadata, 'form_schema.questions.0.id'));
        $this->assertSame('Tujuan', data_get($published->metadata, 'form_schema.questions.0.label'));
        $this->assertTrue((bool) data_get($published->metadata, 'form_schema.questions.0.required'));
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
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Schema</p>',
            ],
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
                'clause' => 4,
                'doc_type' => 'fr',
                'form_schema_json' => json_encode($schema, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('quality.templates.edit', $template));

        $this->assertTrue(session()->has('errors'));
        $this->assertNotEmpty(session('errors')->get('form_schema_json'));
    }

    public function test_update_fr_template_rejects_custom_logo_without_logo_path(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Invalid Logo',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Logo</p>',
            ],
        ]);

        $this->actingAs($user)
            ->from(route('quality.templates.edit', $template))
            ->patch(route('quality.templates.update', $template), [
                'name' => 'Template FR Invalid Logo',
                'clause' => 4,
                'doc_type' => 'fr',
                'logo_source' => 'custom',
                'logo_path' => '',
            ])
            ->assertRedirect(route('quality.templates.edit', $template));

        $this->assertTrue(session()->has('errors'));
        $this->assertNotEmpty(session('errors')->get('logo_path'));
    }

    public function test_activating_template_deactivates_other_active_template_in_same_group(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $first = QmhTemplate::query()->create([
            'name' => 'Template SOP Klausul 4 v1',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>A</p>',
            ],
        ]);

        $second = QmhTemplate::query()->create([
            'name' => 'Template SOP Klausul 4 v2',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 2,
            'storage_disk' => 'local',
            'is_active' => false,
            'metadata' => [
                'content_html' => '<p>B</p>',
            ],
        ]);

        $this->actingAs($user)
            ->patch('/quality/templates/'.$second->id.'/activate')
            ->assertRedirect(route('quality.templates.index'))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Template QMH') && str_contains($message, 'diaktifkan'));

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_activating_fr_template_keeps_other_active_fr_templates_active(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $first = QmhTemplate::query()->create([
            'name' => 'Template FR Structured',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'layout_profile' => 'structured_form',
                'content_html' => '<p>Structured</p>',
            ],
        ]);

        $second = QmhTemplate::query()->create([
            'name' => 'Template FR Risk Matrix',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 2,
            'storage_disk' => 'local',
            'is_active' => false,
            'metadata' => [
                'layout_profile' => 'risk_matrix',
                'content_html' => '<p>Risk</p>',
            ],
        ]);

        $this->actingAs($user)
            ->patch('/quality/templates/'.$second->id.'/activate')
            ->assertRedirect(route('quality.templates.index'));

        $this->assertTrue($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_template_preview_renders_html_preview_and_sanitizes_content(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template XSS',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>OK</p><script>alert("xss")</script><img src="https://evil.example/x">',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.preview', $template))
            ->assertOk()
            ->assertSee('Preview Template (HTML) QMH')
            ->assertSee('Preview ini menggunakan konten HTML template yang dipilih, bukan hasil render PDF final.')
            ->assertSee('OK')
            ->assertDontSee('alert("xss")', false)
            ->assertDontSee('evil.example', false);
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
}
