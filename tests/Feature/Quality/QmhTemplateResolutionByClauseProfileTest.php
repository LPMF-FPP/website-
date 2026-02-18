<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QmhTemplateResolutionByClauseProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_it_resolves_exact_template_by_clause_and_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            [
                'name' => 'FR Clause 7 Risk',
                'clause' => 7,
                'doc_type' => 'fr',
                'shell_mode' => 'full',
                'orientation_policy' => 'landscape',
                'show_signoff_footer' => true,
                'version' => 1,
                'storage_disk' => 'local',
                'is_active' => true,
                'metadata' => json_encode(['layout_profile' => 'risk_matrix'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'FR Clause 7 Structured',
                'clause' => 7,
                'doc_type' => 'fr',
                'shell_mode' => 'full',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => true,
                'version' => 1,
                'storage_disk' => 'local',
                'is_active' => true,
                'metadata' => json_encode(['layout_profile' => 'structured_form'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=fr&clause=7&layout_profile=risk_matrix')
            ->assertOk()
            ->assertJsonPath('resolved_from', 'exact')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'FR Clause 7 Risk')
            ->assertJsonPath('data.0.clause', 7)
            ->assertJsonPath('data.0.layout_profile', 'risk_matrix');
    }

    public function test_it_resolves_fallback_template_when_approved_for_document(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $documentId = DB::table('qmh_documents')->insertGetId([
            'doc_code' => 'QMH-FR-FB-001',
            'title' => 'FR Fallback',
            'clause' => 7,
            'doc_type' => 'formulir',
            'owner_label' => 'Laboratorium',
            'current_revision_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fallbackTemplateId = DB::table('qmh_templates')->insertGetId([
            'name' => 'FR Clause 4 Risk',
            'clause' => 4,
            'doc_type' => 'fr',
            'shell_mode' => 'full',
            'orientation_policy' => 'landscape',
            'show_signoff_footer' => true,
            'version' => 2,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => json_encode(['layout_profile' => 'risk_matrix'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('qmh_template_fallback_requests')->insert([
            'document_id' => $documentId,
            'revision_id' => null,
            'requested_clause' => 7,
            'requested_doc_type' => 'fr',
            'requested_layout_profile' => 'risk_matrix',
            'fallback_clause' => 4,
            'fallback_template_id' => $fallbackTemplateId,
            'status' => 'approved',
            'requested_by' => $user->id,
            'decided_by' => $user->id,
            'decision_note' => 'Approved for transition',
            'requested_at' => now()->subHours(2),
            'decided_at' => now()->subHour(),
            'expires_at' => now()->addHours(20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson("/api/quality/templates?doc_type=fr&clause=7&layout_profile=risk_matrix&document_id={$documentId}")
            ->assertOk()
            ->assertJsonPath('resolved_from', 'fallback')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.clause', 4)
            ->assertJsonPath('data.0.resolved_from', 'fallback');
    }

    private function createQmhPermissions(): void
    {
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
            'permission_id' => $createPermission->id,
        ]);
    }
}
