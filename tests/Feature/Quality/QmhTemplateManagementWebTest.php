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
            ->assertSee('Upload Template');
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
        Storage::disk('local')->assertExists($template->source_docx_path);
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
            ->assertSee('Preview Template DOCX')
            ->assertSee('view.officeapps.live.com');
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
}
