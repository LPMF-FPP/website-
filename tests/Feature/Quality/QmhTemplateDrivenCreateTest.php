<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QmhTemplateDrivenCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_template_list_endpoint_returns_only_active_templates_for_doc_type_and_includes_preview_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            [
                'name' => 'SOP Klausul 4 Aktif',
                'clause' => 4,
                'doc_type' => 'sop',
                'version' => 1,
                'storage_disk' => 'local',
                'source_docx_path' => 'templates/qmh/sop-4.docx',
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SOP Klausul 8 Aktif',
                'clause' => 8,
                'doc_type' => 'sop',
                'version' => 3,
                'storage_disk' => 'local',
                'source_docx_path' => 'templates/qmh/sop-8.docx',
                'is_active' => true,
                'metadata' => json_encode(['content_html' => '<p>Template SOP 8</p>']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SOP Klausul 4 Nonaktif',
                'clause' => 4,
                'doc_type' => 'sop',
                'version' => 1,
                'storage_disk' => 'local',
                'source_docx_path' => 'templates/qmh/sop-4-obsolete.docx',
                'is_active' => false,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'IK Klausul 4 Aktif',
                'clause' => 4,
                'doc_type' => 'ik',
                'version' => 1,
                'storage_disk' => 'local',
                'source_docx_path' => 'templates/qmh/ik-4.docx',
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $activeSopTemplate = DB::table('qmh_templates')
            ->where('name', 'SOP Klausul 8 Aktif')
            ->first();

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=sop');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'SOP Klausul 8 Aktif');
        $response->assertJsonPath('data.0.preview_url', route('quality.templates.preview', $activeSopTemplate->id));
        $response->assertJsonPath('data.0.content_html', '<p>Template SOP 8</p>');
    }

    public function test_create_document_requires_template_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-SOP-TEMPLATE-REQ',
                'title' => 'SOP Uji Wajib Template',
                'clause' => 4,
                'doc_type' => 'sop',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_id']);
    }

    public function test_create_document_persists_selected_template_binding(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $templateId = DB::table('qmh_templates')->insertGetId([
            'name' => 'Template SOP Umum',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 2,
            'storage_disk' => 'local',
            'source_docx_path' => 'templates/qmh/sop-template.docx',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-SOP-TEMPLATE-001',
                'title' => 'SOP dengan Template Office',
                'clause' => 5,
                'doc_type' => 'sop',
                'template_id' => $templateId,
                'change_summary' => 'Draft dari template office',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'template_id' => $templateId,
            'template_version' => 2,
        ]);

        $response->assertJsonPath('data.current_revision.template_id', $templateId);
        $response->assertJsonPath('data.current_revision.template_version', 2);
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

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $viewPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);
    }
}
