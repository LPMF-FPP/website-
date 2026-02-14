<?php

namespace Tests\Feature\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhPdfStructuredTemplateHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_template_contains_structured_header_footer_markers(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-PDF-001',
            'title' => 'SOP Uji PDF',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 2,
            'revision_number' => 4,
            'version_label' => 'E2-R4',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'effective_date' => '2026-02-14',
            'answers_json' => [
                'purpose' => 'Tujuan dokumen.',
            ],
        ]);

        $schema = [
            'version' => 1,
            'doc_type' => 'sop',
            'questions' => [
                ['id' => 'purpose', 'label' => 'Tujuan', 'type' => 'textarea', 'required' => true],
            ],
        ];

        $html = view('pdf.qmh-document', [
            'revision' => $revision->load(['document', 'createdBy', 'reviewedBy', 'approvedBy']),
            'schema' => $schema,
            'answers' => $revision->answers_json,
            'watermarkText' => 'CONTROLLED COPY',
        ])->render();

        $this->assertStringContainsString('No. Dokumen', $html);
        $this->assertStringContainsString('Edisi/Revisi', $html);
        $this->assertStringContainsString('Tgl. Efektif', $html);
        $this->assertStringContainsString('Halaman', $html);
        $this->assertStringContainsString('{PAGE_NUM} DARI {PAGE_COUNT}', $html);
        $this->assertStringContainsString('{PAGE_NUM}/{PAGE_COUNT}', $html);

        $this->assertStringContainsString('Dibuat Oleh:', $html);
        $this->assertStringContainsString('Diperiksa Oleh:', $html);
        $this->assertStringContainsString('Disahkan Oleh:', $html);
    }
}
