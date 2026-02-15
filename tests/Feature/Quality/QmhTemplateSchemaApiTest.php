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
