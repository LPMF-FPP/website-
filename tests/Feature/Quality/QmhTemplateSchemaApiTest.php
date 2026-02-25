<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QmhTemplateSchemaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_template_list_includes_default_form_schema_for_sop_when_missing(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            'name' => 'SOP Default Schema',
            'clause' => 4,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/sop/template.docx',
            'is_active' => true,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=sop');

        $response->assertOk();
        $response->assertJsonCount(8, 'data.0.form_schema.questions');
    }

    public function test_template_list_includes_default_form_schema_for_ik_when_missing(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            'name' => 'IK Default Schema',
            'clause' => 4,
            'doc_type' => 'ik',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/ik/template.docx',
            'is_active' => true,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=ik');

        $response->assertOk();
        $response->assertJsonCount(7, 'data.0.form_schema.questions');
        $response->assertJsonPath('data.0.form_schema.questions.4.id', 'instructions');
        $response->assertJsonPath('data.0.form_schema.questions.5.id', 'required_docs');
    }

    public function test_template_list_includes_layout_profile_and_logo_config_for_fr_template(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            'name' => 'FR Layout Profile',
            'clause' => 4,
            'doc_type' => 'fr',
            'shell_mode' => 'full',
            'orientation_policy' => 'landscape',
            'show_signoff_footer' => true,
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => json_encode([
                'layout_profile' => 'risk_matrix',
                'logo_source' => 'custom',
                'logo_path' => 'images/custom-logo.png',
                'declaration_header' => 'Pernyataan Uji',
                'risk_matrix_columns' => ['Aspek', 'Nilai', 'Kontrol'],
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'risk', 'label' => 'Risiko', 'type' => 'text', 'required' => false],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=formulir');

        $response->assertOk();
        $response->assertJsonPath('data.0.layout_profile', 'risk_matrix');
        $response->assertJsonPath('data.0.layout_profile_runtime', 'risk_matrix');
        $response->assertJsonPath('data.0.is_legacy_layout', false);
        $response->assertJsonPath('data.0.logo_source', 'custom');
        $response->assertJsonPath('data.0.logo_path', 'images/custom-logo.png');
        $response->assertJsonPath('data.0.risk_matrix_columns.2', 'Kontrol');
    }

    public function test_template_list_includes_non_archived_template_even_when_not_marked_active(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            'name' => 'SOP Inactive Legacy',
            'clause' => 5,
            'doc_type' => 'sop',
            'version' => 3,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => false,
            'archived_at' => null,
            'metadata' => json_encode([
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'sop',
                    'questions' => [
                        ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=sop&clause=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'SOP Inactive Legacy');
    }

    public function test_template_list_marks_legacy_fr_template_without_explicit_layout_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            'name' => 'FR Legacy Without Profile',
            'clause' => 4,
            'doc_type' => 'fr',
            'shell_mode' => 'full',
            'orientation_policy' => 'portrait',
            'show_signoff_footer' => true,
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => json_encode([
                'content_html' => '<p>Legacy</p>',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=formulir');

        $response->assertOk();
        $response->assertJsonPath('data.0.layout_profile', 'structured_form');
        $response->assertJsonPath('data.0.layout_profile_runtime', 'legacy');
        $response->assertJsonPath('data.0.is_legacy_layout', true);
    }

    public function test_template_list_can_filter_by_clause_and_layout_profile_for_fr(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            [
                'name' => 'FR Clause 7 Structured',
                'clause' => 7,
                'doc_type' => 'fr',
                'shell_mode' => 'full',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => true,
                'version' => 1,
                'storage_disk' => 'local',
                'source_docx_path' => null,
                'is_active' => true,
                'metadata' => json_encode(['layout_profile' => 'structured_form'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'FR Clause 7 Risk',
                'clause' => 7,
                'doc_type' => 'fr',
                'shell_mode' => 'full',
                'orientation_policy' => 'landscape',
                'show_signoff_footer' => true,
                'version' => 2,
                'storage_disk' => 'local',
                'source_docx_path' => null,
                'is_active' => true,
                'metadata' => json_encode(['layout_profile' => 'risk_matrix'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'FR Clause 4 Risk',
                'clause' => 4,
                'doc_type' => 'fr',
                'shell_mode' => 'full',
                'orientation_policy' => 'landscape',
                'show_signoff_footer' => true,
                'version' => 3,
                'storage_disk' => 'local',
                'source_docx_path' => null,
                'is_active' => true,
                'metadata' => json_encode(['layout_profile' => 'risk_matrix'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=fr&clause=7&layout_profile=risk_matrix');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('resolved_from', 'exact');
        $response->assertJsonPath('data.0.name', 'FR Clause 7 Risk');
        $response->assertJsonPath('data.0.clause', 7);
        $response->assertJsonPath('data.0.layout_profile', 'risk_matrix');
        $response->assertJsonPath('data.0.resolved_from', 'exact');
    }

    public function test_template_list_returns_none_resolution_when_exact_filter_has_no_match(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        DB::table('qmh_templates')->insert([
            'name' => 'FR Clause 4 Structured',
            'clause' => 4,
            'doc_type' => 'fr',
            'shell_mode' => 'full',
            'orientation_policy' => 'portrait',
            'show_signoff_footer' => true,
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => json_encode(['layout_profile' => 'structured_form'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/templates?doc_type=fr&clause=8&layout_profile=declaration');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('resolved_from', 'none');
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
