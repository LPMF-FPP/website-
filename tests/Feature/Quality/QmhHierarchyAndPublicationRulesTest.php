<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Quality\QmhDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhHierarchyAndPublicationRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_ik_requires_parent_sop_when_creating_document(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $template = $this->createTemplate(4, 'ik');

        $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-IK-HIER-001',
                'title' => 'IK tanpa SOP induk',
                'clause' => 4,
                'doc_type' => 'ik',
                'template_id' => $template->id,
                'dibuat_oleh' => $user->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_sop_id']);
    }

    public function test_fr_paired_to_ik_must_share_same_parent_sop(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $sopA = $this->createSopDocument($user, 'QMH-SOP-4.1', 'SOP 4.1');
        $sopB = $this->createSopDocument($user, 'QMH-SOP-4.2', 'SOP 4.2');

        $ikTemplate = $this->createTemplate(4, 'ik');
        $ikResponse = $this->actingAs($user)->postJson('/api/quality/documents', [
            'doc_code' => 'QMH-IK-4.1.1',
            'title' => 'IK 4.1.1',
            'clause' => 4,
            'doc_type' => 'ik',
            'template_id' => $ikTemplate->id,
            'parent_sop_id' => $sopA->id,
            'dibuat_oleh' => $user->id,
        ])->assertCreated();

        $ikId = (int) $ikResponse->json('data.id');
        $frTemplate = $this->createTemplate(4, 'fr');

        $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-FR-INVALID-PAIR',
                'title' => 'FR mismatch parent',
                'clause' => 4,
                'doc_type' => 'fr',
                'template_id' => $frTemplate->id,
                'parent_sop_id' => $sopB->id,
                'paired_ik_id' => $ikId,
                'dibuat_oleh' => $user->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paired_ik_id']);
    }

    public function test_first_publication_of_new_document_remains_e1_r0(): void
    {
        [$revision, $approver] = $this->createRevisionReadyForApproval([
            'doc_code' => 'QMH-SOP-FIRST-PUBLISH',
            'without_previous_published' => true,
        ]);

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'published',
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'version_bump_mode' => 'auto',
        ]);
    }

    /*
    public function test_approval_blocks_ik_publication_without_paired_fr(): void
    {
        [$revision, $approver] = $this->createRevisionReadyForApproval([
            'doc_type' => 'ik',
            'doc_code' => 'QMH-IK-NO-FR',
            'with_parent_sop' => true,
            'without_paired_fr' => true,
        ]);

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paired_fr']);
    }
    */

    private function createTemplate(int $clause, string $docType): QmhTemplate
    {
        return QmhTemplate::query()->create([
            'name' => sprintf('Template %s klausul %d', strtoupper($docType), $clause),
            'clause' => $clause,
            'doc_type' => $docType,
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => sprintf('templates/qmh/%s-%d.docx', $docType, $clause),
            'is_active' => true,
        ]);
    }

    private function createSopDocument(User $creator, string $docCode, string $title): QmhDocument
    {
        $template = $this->createTemplate(4, 'sop');

        return app(QmhDocumentService::class)->createDraft([
            'doc_code' => $docCode,
            'title' => $title,
            'clause' => 4,
            'doc_type' => 'sop',
            'template_id' => $template->id,
            'dibuat_oleh' => $creator->id,
        ], $creator->id);
    }

    /**
     * @return array{0: QmhDocumentRevision, 1: User}
     */
    private function createRevisionReadyForApproval(array $options = []): array
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);

        $docType = (string) ($options['doc_type'] ?? 'sop');
        $storedDocType = $docType === 'fr' ? 'formulir' : $docType;
        $docCode = (string) ($options['doc_code'] ?? ('QMH-APR-'.random_int(1000, 9999)));

        $document = QmhDocument::query()->create([
            'doc_code' => $docCode,
            'title' => 'Dokumen Uji Rule Publish',
            'clause' => 4,
            'doc_type' => $storedDocType,
            'owner_label' => 'Laboratorium',
            'is_active' => true,
            'parent_sop_id' => null,
            'paired_ik_id' => null,
        ]);

        if (($options['with_parent_sop'] ?? false) === true) {
            $sop = QmhDocument::query()->create([
                'doc_code' => 'QMH-SOP-PARENT-'.random_int(1000, 9999),
                'title' => 'SOP Induk',
                'clause' => 4,
                'doc_type' => 'sop',
                'owner_label' => 'Laboratorium',
                'is_active' => true,
            ]);

            $document->update(['parent_sop_id' => $sop->id]);
        }

        if (($options['without_previous_published'] ?? false) !== true) {
            QmhDocumentRevision::query()->create([
                'document_id' => $document->id,
                'edition_number' => 1,
                'revision_number' => 1,
                'version_label' => 'E1-R1',
                'status' => 'published',
                'version_bump_mode' => 'auto',
                'dibuat_oleh' => $creator->id,
                'diperiksa_oleh' => $reviewer->id,
                'disahkan_oleh' => $approver->id,
                'approved_at' => now()->subDay(),
                'effective_date' => now()->subDay()->toDateString(),
            ]);
        }

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'in_approval',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'submitted_at' => now()->subHours(2),
            'reviewed_at' => now()->subHour(),
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        return [$revision, $approver];
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
