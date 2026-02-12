<?php

namespace Tests\Unit\Services\Quality;

use App\Models\User;
use App\Services\Quality\QmhDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QmhDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_draft_creates_document_revision_and_event(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $service = new QmhDocumentService;

        $document = $service->createDraft([
            'doc_code' => 'QMH-FRM-001',
            'title' => 'Formulir Pengujian',
            'clause' => 7,
            'doc_type' => 'formulir',
            'content_html' => '<p>Form awal</p>',
        ], $user->id);

        $this->assertSame('QMH-FRM-001', $document->doc_code);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'status' => 'draft',
            'version_bump_mode' => 'auto',
        ]);

        $this->assertDatabaseHas('qmh_workflow_events', [
            'event_type' => 'create_draft',
            'actor_id' => $user->id,
        ]);
    }

    public function test_create_draft_rolls_back_when_event_logging_fails(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $service = new class extends QmhDocumentService
        {
            protected function persistWorkflowEvent(int $revisionId, int $actorId, array $payload): void
            {
                throw new RuntimeException('forced failure');
            }
        };

        try {
            $service->createDraft([
                'doc_code' => 'QMH-SOP-ROLLBACK',
                'title' => 'SOP Rollback',
                'clause' => 8,
                'doc_type' => 'sop',
            ], $user->id);

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('qmh_documents', [
            'doc_code' => 'QMH-SOP-ROLLBACK',
        ]);
    }
}
