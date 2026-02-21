<?php

namespace Tests\Unit\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\User;
use App\Services\Quality\QmhPendukungService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhPendukungServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_create_generates_version_one_and_hash(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $service = new QmhPendukungService;

        $document = $service->create([
            'doc_code' => 'DP-4.010',
            'title' => 'Diagram Penerimaan',
            'clause' => 4,
        ], UploadedFile::fake()->createWithContent('diagram.pdf', "%PDF-1.4\ncontent"), $user->id);

        $this->assertSame('pendukung', $document->doc_type);

        $revision = QmhDocumentRevision::query()->where('document_id', $document->id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('v1', $revision?->version_label);
        $this->assertNotNull($revision?->file_hash);
    }

    public function test_update_version_increments_to_v2_and_updates_file(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $service = new QmhPendukungService;

        $document = $service->create([
            'doc_code' => 'DP-5.010',
            'title' => 'File Awal',
            'clause' => 5,
        ], UploadedFile::fake()->createWithContent('awal.pdf', "%PDF-1.4\nawal"), $user->id);

        /** @var QmhDocument $document */
        $document = QmhDocument::query()->findOrFail($document->id);

        $updated = $service->updateVersion(
            $document,
            ['title' => 'File Revisi'],
            UploadedFile::fake()->createWithContent('revisi.pdf', "%PDF-1.4\nrevisi"),
            $user->id
        );

        $updated->load('currentRevision');

        $this->assertSame('File Revisi', $updated->title);
        $this->assertSame('v2', $updated->currentRevision?->version_label);
        $this->assertCount(2, QmhDocumentRevision::query()->where('document_id', $updated->id)->get());
    }
}
