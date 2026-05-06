<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhDashboardSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhReportPermission();
    }

    public function test_summary_returns_aggregated_metrics_with_filters(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $matching = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-501',
            'title' => 'SOP Penerimaan Sampel',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);
        $matching->forceFill([
            'created_at' => '2026-02-10 08:00:00',
            'updated_at' => '2026-02-10 08:00:00',
        ])->save();

        $matchingCurrentRevision = QmhDocumentRevision::query()->create([
            'document_id' => $matching->id,
            'edition_number' => 2,
            'revision_number' => 1,
            'version_label' => 'E2-R1',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'created_at' => '2026-02-10 08:00:00',
            'updated_at' => '2026-02-10 08:00:00',
        ]);

        $matching->update(['current_revision_id' => $matchingCurrentRevision->id]);

        QmhDocumentRevision::query()->create([
            'document_id' => $matching->id,
            'edition_number' => 2,
            'revision_number' => 0,
            'version_label' => 'E2-R0',
            'status' => 'obsolete',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'obsolete_at' => '2026-02-15 09:00:00',
            'created_at' => '2026-02-09 08:00:00',
            'updated_at' => '2026-02-15 09:00:00',
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $matching->id,
            'revision_id' => $matchingCurrentRevision->id,
            'edition_number' => 2,
            'revision_number' => 1,
            'copy_type' => 'controlled',
            'downloaded_by' => $user->id,
            'downloaded_at' => '2026-02-20 10:00:00',
            'reason' => null,
            'distribution_target' => 'Lab Utama',
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('a', 64),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $matching->id,
            'revision_id' => $matchingCurrentRevision->id,
            'edition_number' => 2,
            'revision_number' => 1,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $user->id,
            'downloaded_at' => '2026-02-20 10:10:00',
            'reason' => 'Referensi',
            'distribution_target' => null,
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('b', 64),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
        ]);

        $outOfScope = QmhDocument::query()->create([
            'doc_code' => 'QMH-IK-777',
            'title' => 'IK Non Scope',
            'clause' => 6,
            'doc_type' => 'ik',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);
        $outOfScope->forceFill([
            'created_at' => '2026-02-12 08:00:00',
            'updated_at' => '2026-02-12 08:00:00',
        ])->save();

        $outRevision = QmhDocumentRevision::query()->create([
            'document_id' => $outOfScope->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'in_review',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $outOfScope->update(['current_revision_id' => $outRevision->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/dashboard/summary?clause=8&doc_type=sop&from=2026-02-01&to=2026-02-28');

        $response->assertOk()
            ->assertJsonPath('data.total_documents', 1)
            ->assertJsonPath('data.published_documents', 1)
            ->assertJsonPath('data.in_review_documents', 0)
            ->assertJsonPath('data.obsolete_revisions', 1)
            ->assertJsonPath('data.controlled_downloads', 1)
            ->assertJsonPath('data.uncontrolled_downloads', 1);
    }

    public function test_summary_rejects_invalid_filter_values(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->getJson('/api/quality/dashboard/summary?clause=9&doc_type=foo')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['clause', 'doc_type']);
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
