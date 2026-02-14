<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhRevisionApprovalDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();

        // Mock PDF generation
        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('loadHTML')->andReturnSelf();
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('setWarnings')->andReturnSelf();
        $mockPdf->shouldReceive('setOption')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('fake-pdf-content');

        Pdf::shouldReceive('loadHTML')->andReturn($mockPdf);
    }

    public function test_unauthenticated_user_cannot_approve_revision(): void
    {
        [$revision] = $this->createRevisionInApproval();

        $response = $this->postJson("/api/quality/revisions/{$revision->id}/approve", []);

        $response->assertUnauthorized();
    }

    public function test_manual_approve_requires_reason_when_promoting_new_edition(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval();

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve", [
                'promote_to_new_edition' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_approve_marks_previous_published_revision_as_obsolete_and_publishes_new_revision(): void
    {
        [$revision, $approver, $previousPublished] = $this->createRevisionInApproval([
            'previous_edition' => 1,
            'previous_revision' => 3,
        ]);

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve", [])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'published',
            'edition_number' => 1,
            'revision_number' => 4,
            'version_label' => 'E1-R4',
            'version_bump_mode' => 'auto',
        ]);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $previousPublished->id,
            'status' => 'obsolete',
        ]);
    }

    public function test_approve_supports_manual_promotion_and_sets_manual_mode(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval([
            'previous_edition' => 1,
            'previous_revision' => 5,
        ]);

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve", [
                'promote_to_new_edition' => true,
                'reason' => 'Perubahan mayor metode validasi',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'published',
            'edition_number' => 2,
            'revision_number' => 0,
            'version_label' => 'E2-R0',
            'version_bump_mode' => 'manual',
        ]);

        $publishEvent = \App\Models\QmhWorkflowEvent::query()
            ->where('revision_id', $revision->id)
            ->where('event_type', 'publish')
            ->latest('id')
            ->first();

        $this->assertNotNull($publishEvent);
        $this->assertSame('Perubahan mayor metode validasi', $publishEvent->payload_json['manual_reason'] ?? null);
    }

    public function test_controlled_download_requires_published_revision(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval();

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'controlled',
            ])
            ->assertStatus(422);
    }

    public function test_uncontrolled_download_returns_pdf_and_records_download_log(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval();

        $response = $this->actingAs($approver)
            ->withHeaders(['User-Agent' => 'Pest-Test-Agent'])
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'uncontrolled',
                'reason' => 'Referensi diskusi internal',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('qmh_document_download_logs', [
            'revision_id' => $revision->id,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $approver->id,
            'watermark_text' => 'UNCONTROLLED COPY',
        ]);

        $log = QmhDocumentDownloadLog::query()->where('revision_id', $revision->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('Pest-Test-Agent', $log->user_agent);
        $this->assertNotNull($log->file_hash);
        $this->assertSame(64, strlen((string) $log->file_hash));
    }

    public function test_controlled_download_from_published_revision_returns_pdf_and_log(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval([
            'previous_edition' => 1,
            'previous_revision' => 8,
        ]);

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve", [])
            ->assertOk();

        $response = $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'controlled',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('qmh_document_download_logs', [
            'revision_id' => $revision->id,
            'copy_type' => 'controlled',
            'downloaded_by' => $approver->id,
            'watermark_text' => 'CONTROLLED COPY',
        ]);
    }

    /**
     * @return array{0: QmhDocumentRevision, 1: User, 2: QmhDocumentRevision}
     */
    private function createRevisionInApproval(array $options = []): array
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-APR-'.str((string) now()->unix())->append((string) random_int(100, 999)),
            'title' => 'Dokumen Uji Approval',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $previousEdition = (int) ($options['previous_edition'] ?? 1);
        $previousRevision = (int) ($options['previous_revision'] ?? 1);

        $previousPublished = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => $previousEdition,
            'revision_number' => $previousRevision,
            'version_label' => sprintf('E%d-R%d', $previousEdition, $previousRevision),
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'approved_at' => now()->subDay(),
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'in_approval',
            'content_html' => '<h1>Dokumen Uji</h1><p>Konten revisi.</p>',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'submitted_at' => now()->subHours(2),
            'reviewed_at' => now()->subHour(),
        ]);

        $document->current_revision_id = $revision->id;
        $document->save();

        return [$revision, $approver, $previousPublished];
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
