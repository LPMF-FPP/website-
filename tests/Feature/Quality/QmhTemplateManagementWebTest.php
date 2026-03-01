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

    public function test_template_management_page_is_removed_and_returns_404(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality/templates')
            ->assertNotFound();
    }

    public function test_template_management_edit_page_is_removed_and_returns_404(): void
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
            ->get('/quality/templates/'.$template->id.'/edit')
            ->assertNotFound();
    }

    public function test_template_management_mutation_endpoints_are_removed_and_return_404(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template SOP v1',
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
            ->post('/quality/templates', [
                'name' => 'Template SOP HTML-only',
                'clause' => 4,
                'doc_type' => 'sop',
                'version_notes' => 'editor-first',
                'content_html' => '<h1>Judul</h1><p>Isi</p>',
            ])
            ->assertNotFound();

        $this->actingAs($user)
            ->patch('/quality/templates/'.$template->id, [
                'name' => 'Template SOP v2',
                'clause' => 4,
                'doc_type' => 'sop',
                'content_html' => '<p>Baru</p>',
            ])
            ->assertNotFound();

        $this->actingAs($user)
            ->patch('/quality/templates/'.$template->id.'/activate')
            ->assertNotFound();

        $this->actingAs($user)
            ->patch('/quality/templates/'.$template->id.'/deactivate')
            ->assertNotFound();
    }

    public function test_template_preview_still_renders_html_preview_and_sanitizes_content(): void
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

    public function test_template_preview_decodes_legacy_escaped_html_content(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template Escaped Legacy',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>&lt;h1&gt;Judul Legacy&lt;/h1&gt;&lt;p&gt;Isi Legacy&lt;/p&gt;</p>',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.preview', $template))
            ->assertOk()
            ->assertSee('Judul Legacy')
            ->assertSee('Isi Legacy')
            ->assertDontSee('&lt;h1&gt;Judul Legacy&lt;/h1&gt;', false);
    }

    public function test_user_without_create_or_template_manage_permission_cannot_access_preview(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $template = QmhTemplate::query()->create([
            'name' => 'Template SOP Restricted',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Konten</p>',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('quality.templates.preview', $template))
            ->assertForbidden();
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
