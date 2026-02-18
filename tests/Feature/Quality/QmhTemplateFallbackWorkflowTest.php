<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QmhTemplateFallbackWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPermissions();
    }

    public function test_request_and_review_fallback_workflow_enables_fallback_resolution(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $manager */
        $manager = User::factory()->create(['role' => 'qmh_manager']);

        $fallbackTemplateId = DB::table('qmh_templates')->insertGetId([
            'name' => 'FR Clause 4 Structured',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => json_encode(['layout_profile' => 'structured_form'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-REQ-FB-001',
            'title' => 'Dokumen FR Fallback',
            'clause' => 7,
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
            'dibuat_oleh' => $creator->id,
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/template-fallback/request", [
                'layout_profile' => 'structured_form',
                'note' => 'Template klausul 7 belum ada',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'requested');

        $this->actingAs($manager)
            ->postJson("/api/quality/revisions/{$revision->id}/template-fallback/review", [
                'action' => 'approve',
                'fallback_template_id' => $fallbackTemplateId,
                'note' => 'Approve transisi',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->actingAs($creator)
            ->getJson('/api/quality/templates?doc_type=fr&clause=7&layout_profile=structured_form&document_id='.$document->id)
            ->assertOk()
            ->assertJsonPath('resolved_from', 'fallback')
            ->assertJsonPath('data.0.clause', 4);
    }

    public function test_expire_command_marks_overdue_requested_fallbacks_as_expired(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-REQ-FB-EXPIRED',
            'title' => 'Dokumen FR Fallback Expired',
            'clause' => 8,
            'doc_type' => 'formulir',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        DB::table('qmh_template_fallback_requests')->insert([
            'document_id' => $document->id,
            'revision_id' => null,
            'requested_clause' => 8,
            'requested_doc_type' => 'fr',
            'requested_layout_profile' => 'structured_form',
            'fallback_clause' => 4,
            'fallback_template_id' => null,
            'status' => 'requested',
            'requested_by' => $creator->id,
            'requested_at' => now()->subDays(2),
            'expires_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('qmh:fallback:expire')
            ->expectsOutput('Expired fallback requests: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('qmh_template_fallback_requests', [
            'document_id' => $document->id,
            'status' => 'expired',
        ]);
    }

    private function createPermissions(): void
    {
        $createPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.create'],
            [
                'display_name' => 'Buat Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'create',
            ]
        );

        $templateManage = Permission::query()->updateOrCreate(
            ['name' => 'qmh.template.manage'],
            [
                'display_name' => 'Kelola Template Quality Management Hub',
                'module' => 'qmh',
                'action' => 'template-manage',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $templateManage->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'qmh_manager',
            'permission_id' => $templateManage->id,
        ]);
    }
}
