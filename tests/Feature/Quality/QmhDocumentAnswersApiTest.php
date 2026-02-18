<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhDocumentAnswersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_create_document_persists_answers_json_on_current_revision(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        Storage::disk('local')->put('qmh/templates/sop/template.docx', 'dummy');

        $templateId = DB::table('qmh_templates')->insertGetId([
            'name' => 'SOP Template',
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

        $payload = [
            'doc_code' => 'QMH-SOP-ANS-001',
            'title' => 'SOP Uji Terstruktur',
            'clause' => 4,
            'doc_type' => 'sop',
            'template_id' => $templateId,
            'dibuat_oleh' => $user->id,
            'answers_json' => [
                'purpose' => 'Menjelaskan tujuan prosedur.',
                'scope' => 'Berlaku untuk semua sampel.',
                'procedure' => 'Langkah 1: ...',
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/quality/documents', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.current_revision.answers_json.purpose', 'Menjelaskan tujuan prosedur.');

        $documentId = (int) $response->json('data.id');
        $this->assertDatabaseHas('qmh_document_revisions', [
            'document_id' => $documentId,
            'effective_date' => null,
            'dibuat_oleh' => $user->id,
        ]);
    }

    public function test_save_revision_content_can_persist_answers_json_ignoring_effective_date_input(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        Storage::disk('local')->put('qmh/templates/sop/template.docx', 'dummy');

        $templateId = DB::table('qmh_templates')->insertGetId([
            'name' => 'SOP Template',
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

        $createResponse = $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-SOP-ANS-002',
                'title' => 'SOP Edit Terstruktur',
                'clause' => 4,
                'doc_type' => 'sop',
                'template_id' => $templateId,
                'dibuat_oleh' => $user->id,
            ]);

        $createResponse->assertCreated();

        $revisionId = (int) $createResponse->json('data.current_revision.id');
        $this->assertGreaterThan(0, $revisionId);

        $this->actingAs($user)
            ->postJson('/api/quality/revisions/'.$revisionId.'/lock', [])
            ->assertOk();

        $this->actingAs($user)
            ->putJson('/api/quality/revisions/'.$revisionId.'/content', [
                'content_version' => 1,
                'content_html' => '<p></p>',
                'answers_json' => [
                    'purpose' => 'Tujuan revisi',
                    'procedure' => 'Langkah revisi',
                ],
                'effective_date' => '2026-03-01',
            ])
            ->assertOk();

        $revision = QmhDocumentRevision::query()->findOrFail($revisionId);

        $this->assertSame('Tujuan revisi', data_get($revision->answers_json, 'purpose'));
        $this->assertNull($revision->effective_date);
    }

    public function test_create_document_allows_rich_text_answers_and_preserves_ordered_lists(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        Storage::disk('local')->put('qmh/templates/sop/template.docx', 'dummy');

        $templateId = DB::table('qmh_templates')->insertGetId([
            'name' => 'SOP Template Rich Text',
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

        $payload = [
            'doc_code' => 'QMH-SOP-ANS-HTML-001',
            'title' => 'SOP Uji Rich Text',
            'clause' => 4,
            'doc_type' => 'sop',
            'template_id' => $templateId,
            'dibuat_oleh' => $user->id,
            'answers_json' => [
                'purpose' => '<p><strong>Tujuan</strong> <em>dokumen</em></p>',
                'definitions' => '<ol><li><p>Definisi 1</p></li><li><p>Definisi 2</p></li></ol>',
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/quality/documents', $payload);

        $response->assertCreated();

        $answers = $response->json('data.current_revision.answers_json');
        $this->assertIsArray($answers);
        $this->assertIsString($answers['purpose'] ?? null);
        $this->assertIsString($answers['definitions'] ?? null);

        $this->assertStringContainsString('<strong>', (string) ($answers['purpose'] ?? ''));
        $this->assertStringContainsString('<em>', (string) ($answers['purpose'] ?? ''));
        $this->assertStringContainsString('<ol', (string) ($answers['definitions'] ?? ''));
        $this->assertStringContainsString('Definisi 2', (string) ($answers['definitions'] ?? ''));
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
