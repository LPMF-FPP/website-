<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_unauthenticated_user_cannot_access_quality_documents_api(): void
    {
        $response = $this->getJson('/api/quality/documents');

        $response->assertUnauthorized();
    }

    public function test_user_without_qmh_permission_gets_forbidden_response(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/documents');

        $response->assertForbidden();
    }

    public function test_can_create_document_with_initial_revision_and_workflow_event(): void
    {
        /** @var User $user */
        $user = $this->createAdminWithQmhPermissions();

        $response = $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-SOP-001',
                'title' => 'SOP Penerimaan Sampel',
                'clause' => 8,
                'doc_type' => 'sop',
                'change_summary' => 'Dokumen awal',
                'content_html' => '<p>Konten awal</p>',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.doc_code', 'QMH-SOP-001');

        $this->assertDatabaseHas('qmh_documents', [
            'doc_code' => 'QMH-SOP-001',
            'title' => 'SOP Penerimaan Sampel',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
        ]);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'edition_number' => 1,
            'revision_number' => 0,
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $this->assertDatabaseHas('qmh_workflow_events', [
            'event_type' => 'create_draft',
            'actor_id' => $user->id,
        ]);
    }

    public function test_create_document_requires_unique_doc_code(): void
    {
        /** @var User $user */
        $user = $this->createAdminWithQmhPermissions();

        $payload = [
            'doc_code' => 'QMH-IK-001',
            'title' => 'IK Kalibrasi Neraca',
            'clause' => 6,
            'doc_type' => 'ik',
        ];

        $this->actingAs($user)
            ->postJson('/api/quality/documents', $payload)
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/quality/documents', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['doc_code']);
    }

    public function test_document_list_supports_search_and_filters(): void
    {
        /** @var User $user */
        $user = $this->createAdminWithQmhPermissions();

        $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-SOP-010',
                'title' => 'SOP Penerimaan Barang Bukti',
                'clause' => 8,
                'doc_type' => 'sop',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-IK-004',
                'title' => 'IK Kalibrasi Neraca',
                'clause' => 6,
                'doc_type' => 'ik',
            ])
            ->assertCreated();

        $response = $this->actingAs($user)
            ->getJson('/api/quality/documents?search=Kalibrasi&clause=6&doc_type=ik&status=draft&edition_number=1&revision_number=0');

        $response->assertOk()
            ->assertJsonFragment([
                'doc_code' => 'QMH-IK-004',
            ]);

        $rows = collect($response->json('data'));

        $this->assertCount(1, $rows);
        $this->assertSame('QMH-IK-004', $rows->first()['doc_code']);
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

    private function createAdminWithQmhPermissions(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }
}
