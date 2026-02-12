<?php

namespace Tests\Unit\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhWorkflowEvent;
use App\Models\User;
use App\Services\Quality\QmhReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_history_returns_audit_fields_with_status_transition(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-UNIT-2',
            'title' => 'SOP Unit Reporting',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 2,
            'revision_number' => 0,
            'version_label' => 'E2-R0',
            'status' => 'in_approval',
            'version_bump_mode' => 'manual',
            'dibuat_oleh' => $actor->id,
        ]);

        QmhWorkflowEvent::query()->create([
            'revision_id' => $revision->id,
            'event_type' => 'review_pass',
            'actor_id' => $actor->id,
            'payload_json' => [
                'status_transition' => 'in_review -> in_approval',
                'reason' => 'Siap disahkan',
            ],
        ]);

        $service = new QmhReportingService;

        $paginator = $service->revisionHistory([
            'clause' => 8,
            'doc_type' => 'sop',
            'per_page' => 10,
        ]);

        $row = $paginator->items()[0];

        $this->assertSame('QMH-SOP-UNIT-2', $row->document_code);
        $this->assertSame('SOP Unit Reporting', $row->document_title);
        $this->assertSame('E2-R0', $row->version_label);
        $this->assertSame('in_review -> in_approval', $row->status_transition);
        $this->assertSame('Siap disahkan', $row->reason);
    }

    public function test_download_history_controlled_only_filters_rows(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-UNIT-3',
            'title' => 'SOP Unit Download',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'version_label' => 'E1-R2',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $revision->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'copy_type' => 'controlled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => now(),
            'distribution_target' => 'Unit A',
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('3', 64),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $revision->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $actor->id,
            'downloaded_at' => now(),
            'reason' => 'Referensi',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('4', 64),
        ]);

        $service = new QmhReportingService;

        $paginator = $service->downloadHistory([
            'clause' => 8,
            'doc_type' => 'sop',
            'per_page' => 10,
        ], true);

        $this->assertSame(1, $paginator->total());
        $this->assertSame('controlled', $paginator->items()[0]->copy_type);
    }
}
