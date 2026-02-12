<?php

namespace Tests\Unit\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\User;
use App\Services\Quality\QmhDashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhDashboardSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_expected_counts_for_filtered_scope(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-UNIT-1',
            'title' => 'SOP Unit Summary',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
            'created_at' => '2026-02-10 08:00:00',
            'updated_at' => '2026-02-10 08:00:00',
        ]);

        $currentRevision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 4,
            'version_label' => 'E1-R4',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $document->update(['current_revision_id' => $currentRevision->id]);

        QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 3,
            'version_label' => 'E1-R3',
            'status' => 'obsolete',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'obsolete_at' => '2026-02-12 09:00:00',
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $currentRevision->id,
            'edition_number' => 1,
            'revision_number' => 4,
            'copy_type' => 'controlled',
            'downloaded_by' => $user->id,
            'downloaded_at' => '2026-02-13 10:00:00',
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('1', 64),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $currentRevision->id,
            'edition_number' => 1,
            'revision_number' => 4,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $user->id,
            'downloaded_at' => '2026-02-13 10:05:00',
            'reason' => 'Referensi unit test',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('2', 64),
        ]);

        $service = new QmhDashboardSummaryService;

        $summary = $service->summarize([
            'clause' => 8,
            'doc_type' => 'sop',
            'from' => '2026-02-01',
            'to' => '2026-02-28',
        ]);

        $this->assertSame(1, $summary['total_documents']);
        $this->assertSame(1, $summary['published_documents']);
        $this->assertSame(0, $summary['in_review_documents']);
        $this->assertSame(1, $summary['obsolete_revisions']);
        $this->assertSame(1, $summary['controlled_downloads']);
        $this->assertSame(1, $summary['uncontrolled_downloads']);
    }
}
