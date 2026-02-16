<?php

namespace Tests\Unit\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\User;
use App\Services\Quality\QmhRevisionDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhRevisionDownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_watermarked_html_contains_expected_watermark_and_version_label(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create([
            'name' => 'Gifari',
            'role' => 'admin',
            'rank' => 'Penata TK I',
            'nrp' => '12345678',
            'nip' => '19876543210001',
        ]);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-DL-001',
            'title' => 'Dokumen Download Test',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 2,
            'revision_number' => 4,
            'version_label' => 'E2-R4',
            'status' => 'published',
            'content_html' => '<p>Konten PDF uji watermark.</p>',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
        ]);

        $service = new QmhRevisionDownloadService;
        $html = $service->buildWatermarkedHtml($revision, 'SALINAN TERKENDALI');

        $this->assertStringContainsString('SALINAN TERKENDALI', $html);
        $this->assertStringContainsString('E2/R4', $html);
        $this->assertStringContainsString('Konten PDF uji watermark.', $html);

        // Structured header/footer markers (system-generated)
        $this->assertStringContainsString('No. Dokumen', $html);
        $this->assertStringContainsString('Edisi/Revisi', $html);
        $this->assertStringContainsString('Tgl. Efektif', $html);
        $this->assertStringContainsString('Halaman', $html);
        $this->assertStringContainsString('Dibuat Oleh', $html);
        $this->assertStringContainsString('Diperiksa Oleh', $html);
        $this->assertStringContainsString('Disahkan Oleh', $html);
        $this->assertStringContainsString('Nama/Pangkat', $html);
        $this->assertStringContainsString('Gifari/Penata TK I', $html);
        $this->assertStringContainsString('Jabatan', $html);
        $this->assertStringContainsString('Admin', $html);
    }
}
