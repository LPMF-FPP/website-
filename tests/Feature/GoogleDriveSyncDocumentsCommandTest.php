<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\TestRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveSyncDocumentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_only_retryable_uploaded_documents_for_request(): void
    {
        $testRequest = TestRequest::factory()->create();

        $skipped = Document::factory()->create([
            'test_request_id' => $testRequest->id,
            'source' => 'upload',
            'document_type' => 'request_letter',
            'extra' => ['google_drive' => ['status' => 'skipped']],
        ]);
        $failed = Document::factory()->create([
            'test_request_id' => $testRequest->id,
            'source' => 'upload',
            'document_type' => 'expert_witness_request',
            'extra' => ['google_drive' => ['status' => 'failed']],
        ]);
        $uploaded = Document::factory()->create([
            'test_request_id' => $testRequest->id,
            'source' => 'upload',
            'document_type' => 'evidence_photo',
            'extra' => ['google_drive' => ['status' => 'uploaded']],
        ]);
        $generated = Document::factory()->generated()->create([
            'test_request_id' => $testRequest->id,
            'document_type' => 'ba_penerimaan',
            'extra' => ['google_drive' => ['status' => 'skipped']],
        ]);

        $this->artisan('lims:google-drive-sync-documents', [
            '--request' => $testRequest->id,
            '--dry-run' => true,
        ])
            ->expectsTable(
                ['ID', 'Permintaan', 'Tipe', 'File', 'Status Drive'],
                [
                    [$skipped->id, $testRequest->id, 'request_letter', $skipped->filename, 'skipped'],
                    [$failed->id, $testRequest->id, 'expert_witness_request', $failed->filename, 'failed'],
                ],
            )
            ->expectsOutput('Dry run selesai; tidak ada upload yang dilakukan.')
            ->assertExitCode(0);

        $this->assertSame('uploaded', data_get($uploaded->fresh()->extra, 'google_drive.status'));
        $this->assertSame('skipped', data_get($generated->fresh()->extra, 'google_drive.status'));
    }
}
