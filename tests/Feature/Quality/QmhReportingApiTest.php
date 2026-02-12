<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhReportingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhReportPermission();
    }

    public function test_revision_history_endpoint_returns_paginated_audit_rows(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-901',
            'title' => 'SOP Histori Revisi',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 3,
            'version_label' => 'E1-R3',
            'status' => 'in_review',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        QmhWorkflowEvent::query()->create([
            'revision_id' => $revision->id,
            'event_type' => 'submit_review',
            'actor_id' => $actor->id,
            'payload_json' => [
                'reason' => 'Perbaikan redaksi',
                'status_transition' => 'in_review -> in_approval',
            ],
            'created_at' => '2026-02-12 08:00:00',
        ]);

        $response = $this->actingAs($actor)
            ->getJson('/api/quality/reports/revision-history?clause=8&doc_type=sop&per_page=10');

        $response->assertOk()
            ->assertJsonPath('data.0.document_code', 'QMH-SOP-901')
            ->assertJsonPath('data.0.document_title', 'SOP Histori Revisi')
            ->assertJsonPath('data.0.version_label', 'E1-R3')
            ->assertJsonPath('data.0.status_transition', 'in_review -> in_approval')
            ->assertJsonPath('data.0.reason', 'Perbaikan redaksi')
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    [
                        'actor_id',
                        'actor_name',
                        'document_code',
                        'document_title',
                        'version_label',
                        'status_transition',
                        'reason',
                        'occurred_at',
                    ],
                ],
                'per_page',
                'total',
            ]);
    }

    public function test_download_history_endpoint_returns_paginated_audit_rows(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        [$document, $revision] = $this->createDocumentWithRevision($actor, 'QMH-SOP-920', 8, 'sop');

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $revision->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => '2026-02-13 09:00:00',
            'reason' => 'Audit internal',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('c', 64),
        ]);

        $response = $this->actingAs($actor)
            ->getJson('/api/quality/reports/download-history?clause=8&doc_type=sop&per_page=10');

        $response->assertOk()
            ->assertJsonPath('data.0.document_code', 'QMH-SOP-920')
            ->assertJsonPath('data.0.copy_type', 'uncontrolled')
            ->assertJsonPath('data.0.reason', 'Audit internal')
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    [
                        'actor_id',
                        'actor_name',
                        'document_code',
                        'document_title',
                        'version_label',
                        'copy_type',
                        'reason',
                        'occurred_at',
                    ],
                ],
                'per_page',
                'total',
            ]);
    }

    public function test_controlled_distribution_endpoint_only_returns_controlled_copy_rows(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        [$document, $revision] = $this->createDocumentWithRevision($actor, 'QMH-SOP-930', 8, 'sop');

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $revision->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'copy_type' => 'controlled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => '2026-02-14 10:00:00',
            'distribution_target' => 'Gudang A',
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('d', 64),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $revision->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => '2026-02-14 11:00:00',
            'reason' => 'Referensi',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('e', 64),
        ]);

        $response = $this->actingAs($actor)
            ->getJson('/api/quality/reports/controlled-distribution?per_page=10');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.copy_type', 'controlled')
            ->assertJsonPath('data.0.distribution_target', 'Gudang A');
    }

    public function test_report_endpoints_reject_invalid_filters(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        $this->actingAs($actor)
            ->getJson('/api/quality/reports/revision-history?per_page=500&clause=9')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page', 'clause']);
    }

    public function test_revision_history_excludes_download_event_type_rows(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-942',
            'title' => 'SOP Event Filter',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        QmhWorkflowEvent::query()->create([
            'revision_id' => $revision->id,
            'event_type' => 'download',
            'actor_id' => $actor->id,
            'payload_json' => [],
        ]);

        QmhWorkflowEvent::query()->create([
            'revision_id' => $revision->id,
            'event_type' => 'publish',
            'actor_id' => $actor->id,
            'payload_json' => [
                'status_transition' => 'in_approval -> published',
            ],
        ]);

        $response = $this->actingAs($actor)
            ->getJson('/api/quality/reports/revision-history?per_page=10');

        $response->assertOk();
        $this->assertSame(1, count($response->json('data')));
        $response->assertJsonPath('data.0.status_transition', 'in_approval -> published');
    }

    public function test_revision_history_export_returns_csv_with_active_filters(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        $matchingDocument = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-940',
            'title' => 'SOP Export Revisi',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $matchingRevision = QmhDocumentRevision::query()->create([
            'document_id' => $matchingDocument->id,
            'edition_number' => 1,
            'revision_number' => 1,
            'version_label' => 'E1-R1',
            'status' => 'in_approval',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        QmhWorkflowEvent::query()->create([
            'revision_id' => $matchingRevision->id,
            'event_type' => 'review_pass',
            'actor_id' => $actor->id,
            'payload_json' => ['status_transition' => 'in_review -> in_approval'],
        ]);

        $otherDocument = QmhDocument::query()->create([
            'doc_code' => 'QMH-IK-941',
            'title' => 'IK Tidak Masuk Filter',
            'clause' => 6,
            'doc_type' => 'ik',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $otherRevision = QmhDocumentRevision::query()->create([
            'document_id' => $otherDocument->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        QmhWorkflowEvent::query()->create([
            'revision_id' => $otherRevision->id,
            'event_type' => 'create_draft',
            'actor_id' => $actor->id,
            'payload_json' => [],
        ]);

        $response = $this->actingAs($actor)
            ->get('/api/quality/reports/revision-history/export?clause=8&doc_type=sop');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();

        $this->assertStringContainsString('QMH-SOP-940', $content);
        $this->assertStringNotContainsString('QMH-IK-941', $content);
    }

    public function test_download_history_export_returns_csv_with_active_filters(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        [$matchingDocument, $matchingRevision] = $this->createDocumentWithRevision($actor, 'QMH-SOP-950', 8, 'sop');
        [$otherDocument, $otherRevision] = $this->createDocumentWithRevision($actor, 'QMH-IK-951', 6, 'ik');

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $matchingDocument->id,
            'revision_id' => $matchingRevision->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'copy_type' => 'controlled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => '2026-02-14 09:00:00',
            'distribution_target' => 'Ruang Sampling',
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('f', 64),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $otherDocument->id,
            'revision_id' => $otherRevision->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => '2026-02-14 09:10:00',
            'reason' => 'Referensi',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('0', 64),
        ]);

        $response = $this->actingAs($actor)
            ->get('/api/quality/reports/download-history/export?clause=8&doc_type=sop');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = (string) $response->getContent();

        $this->assertStringContainsString('QMH-SOP-950', $content);
        $this->assertStringNotContainsString('QMH-IK-951', $content);
    }

    public function test_controlled_distribution_export_returns_only_controlled_rows(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        [$document, $revision] = $this->createDocumentWithRevision($actor, 'QMH-SOP-952', 8, 'sop');

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $revision->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'copy_type' => 'controlled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => '2026-02-14 09:00:00',
            'distribution_target' => 'Unit Terkendali',
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('1', 64),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $revision->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => '2026-02-14 09:10:00',
            'reason' => 'Referensi',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('2', 64),
        ]);

        $response = $this->actingAs($actor)
            ->get('/api/quality/reports/controlled-distribution/export');

        $response->assertOk();

        $content = (string) $response->getContent();

        $this->assertStringContainsString('Unit Terkendali', $content);
        $this->assertStringNotContainsString('UNCONTROLLED COPY', $content);
    }

    /**
     * @return array{0: QmhDocument, 1: QmhDocumentRevision}
     */
    private function createDocumentWithRevision(User $actor, string $docCode, int $clause, string $docType): array
    {
        $document = QmhDocument::query()->create([
            'doc_code' => $docCode,
            'title' => 'Dokumen '.$docCode,
            'clause' => $clause,
            'doc_type' => $docType,
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        return [$document, $revision];
    }

    private function createQmhReportPermission(): void
    {
        $reportPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.report'],
            [
                'display_name' => 'Lihat Laporan Quality Management Hub',
                'module' => 'qmh',
                'action' => 'report',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $reportPermission->id,
        ]);
    }
}
