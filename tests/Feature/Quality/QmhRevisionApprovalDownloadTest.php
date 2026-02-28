<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Quality\QmhRevisionDownloadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhRevisionApprovalDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();

        $mockCanvas = \Mockery::mock();
        $mockCanvas->shouldReceive('get_page_count')->andReturn(2);

        $mockDompdf = \Mockery::mock();
        $mockDompdf->shouldReceive('getCanvas')->andReturn($mockCanvas);

        // Mock PDF generation
        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('setWarnings')->andReturnSelf();
        $mockPdf->shouldReceive('setOption')->andReturnSelf();
        $mockPdf->shouldReceive('render')->andReturnNull();
        $mockPdf->shouldReceive('getDomPDF')->andReturn($mockDompdf);
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

    public function test_approve_rejects_when_checker_returns_fail(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval();

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve", [
                'checker_status' => 'fail',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['checker_status']);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'in_approval',
        ]);
    }

    public function test_approve_allows_checker_unavailable_with_attestation_permission(): void
    {
        $this->grantAttestationPermissionToAdminRole();
        [$revision, $approver] = $this->createRevisionInApproval();

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve", [
                'checker_status' => 'unavailable',
                'attestation_actor' => 'Kepala Lab',
                'attestation_reason' => 'Checker timeout saat jam sibuk',
                'incident_ref' => 'INC-2026-0021',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'published',
            'layout_checker_status' => 'unavailable',
            'attestation_actor' => 'Kepala Lab',
            'attestation_incident_ref' => 'INC-2026-0021',
        ]);

        $this->assertDatabaseHas('qmh_workflow_events', [
            'revision_id' => $revision->id,
            'event_type' => 'attestation_fallback',
        ]);
    }

    public function test_approve_rejects_checker_unavailable_without_attestation_permission(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval();

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/approve", [
                'checker_status' => 'unavailable',
                'attestation_actor' => 'Kepala Lab',
                'attestation_reason' => 'Checker timeout saat jam sibuk',
                'incident_ref' => 'INC-2026-0022',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['checker_status']);
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
            'watermark_text' => 'SALINAN TIDAK TERKENDALI',
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

        $revision = $revision->fresh('document');
        $expectedFilename = sprintf(
            '%s - %s - %s - TERKENDALI.pdf',
            (string) ($revision?->document?->doc_code ?? '-'),
            (string) ($revision?->document?->title ?? '-'),
            (string) ($revision?->version_label ?? '-')
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="'.$expectedFilename.'"');

        $this->assertDatabaseHas('qmh_document_download_logs', [
            'revision_id' => $revision->id,
            'copy_type' => 'controlled',
            'downloaded_by' => $approver->id,
            'watermark_text' => 'SALINAN TERKENDALI',
        ]);
    }

    public function test_fr_table_download_filename_matches_final_format(): void
    {
        [$revision, $approver] = $this->createFrRevisionInApprovalWithRealSourcePdf();

        $response = $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'uncontrolled',
                'reason' => 'Validasi format nama file FR tabel',
            ]);

        $revision = $revision->fresh('document');
        $expectedFilename = sprintf(
            '%s - %s - TABEL - %s - TIDAK TERKENDALI.pdf',
            (string) ($revision?->document?->doc_code ?? '-'),
            (string) ($revision?->document?->title ?? '-'),
            (string) ($revision?->version_label ?? '-')
        );

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="'.$expectedFilename.'"');
    }

    public function test_fr_non_table_download_filename_matches_final_format(): void
    {
        [$revision, $approver] = $this->createFrRevisionInApprovalWithRealSourcePdf();

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Non Tabel',
            'clause' => 4,
            'version' => 1,
            'doc_type' => 'fr',
            'is_active' => true,
            'metadata' => [
                'layout_profile' => 'declaration',
                'shell_mode' => 'body_only',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => false,
            ],
        ]);

        $revision->template_id = $template->id;
        $revision->save();

        $response = $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'uncontrolled',
                'reason' => 'Validasi format nama file FR non tabel',
            ]);

        $revision = $revision->fresh('document');
        $expectedFilename = sprintf(
            '%s - %s - NON TABEL - %s - TIDAK TERKENDALI.pdf',
            (string) ($revision?->document?->doc_code ?? '-'),
            (string) ($revision?->document?->title ?? '-'),
            (string) ($revision?->version_label ?? '-')
        );

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="'.$expectedFilename.'"');
    }

    public function test_sop_download_filename_matches_final_format_without_table_segment(): void
    {
        [$revision, $approver] = $this->createRevisionInApproval();

        $response = $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'uncontrolled',
                'reason' => 'Validasi format nama file SOP',
            ]);

        $revision = $revision->fresh('document');
        $expectedFilename = sprintf(
            '%s - %s - %s - TIDAK TERKENDALI.pdf',
            (string) ($revision?->document?->doc_code ?? '-'),
            (string) ($revision?->document?->title ?? '-'),
            (string) ($revision?->version_label ?? '-')
        );

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="'.$expectedFilename.'"');
    }

    public function test_ik_download_filename_matches_final_format_without_table_segment(): void
    {
        [$revision, $approver] = $this->createIkRevisionInApproval();

        $response = $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'uncontrolled',
                'reason' => 'Validasi format nama file IK',
            ]);

        $revision = $revision->fresh('document');
        $expectedFilename = sprintf(
            '%s - %s - %s - TIDAK TERKENDALI.pdf',
            (string) ($revision?->document?->doc_code ?? '-'),
            (string) ($revision?->document?->title ?? '-'),
            (string) ($revision?->version_label ?? '-')
        );

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="'.$expectedFilename.'"');
    }

    public function test_fr_source_pdf_download_falls_back_to_dompdf_when_source_pdf_missing_after_submit(): void
    {
        [$revision, $approver] = $this->createFrRevisionInApprovalWithSourcePdfMetadata();

        $service = \Mockery::mock(QmhRevisionDownloadService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('canUseFpdiSourcePipeline')->andReturn(true);
        app()->instance(QmhRevisionDownloadService::class, $service);

        $response = $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'uncontrolled',
                'reason' => 'Uji fallback runtime FPDI',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('qmh_document_download_logs', [
            'revision_id' => $revision->id,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $approver->id,
            'watermark_text' => 'SALINAN TIDAK TERKENDALI',
        ]);
    }

    public function test_fr_source_pdf_download_uses_uploaded_pdf_when_source_metadata_is_valid(): void
    {
        [$revision, $approver] = $this->createFrRevisionInApprovalWithRealSourcePdf();

        $response = $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/download", [
                'copy_type' => 'uncontrolled',
                'reason' => 'Validasi render file sumber FR-v2',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $content = (string) $response->getContent();
        $this->assertNotSame('fake-pdf-content', $content, 'Download masih fallback ke DomPDF padahal source PDF valid.');
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_fr_source_pdf_layout_defaults_to_full_shell_when_schema_snapshot_omits_layout_keys(): void
    {
        [$revision] = $this->createFrRevisionInApprovalWithRealSourcePdf();

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Declaration',
            'clause' => 4,
            'version' => 1,
            'doc_type' => 'fr',
            'is_active' => true,
            'metadata' => [
                'layout_profile' => 'declaration',
                'shell_mode' => 'body_only',
                'orientation_policy' => 'portrait',
                'show_signoff_footer' => false,
            ],
        ]);

        $revision->template_id = $template->id;
        $revision->form_schema_json = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [],
        ];
        $revision->save();

        $service = app(QmhRevisionDownloadService::class);

        $method = new \ReflectionMethod($service, 'resolveFrLayoutConfig');
        $method->setAccessible(true);

        /** @var array{shell_mode: string, orientation_policy: string, show_signoff_footer: bool} $resolved */
        $resolved = $method->invoke($service, $revision->fresh(['document', 'template']), $revision->form_schema_json ?? []);

        $this->assertSame('full', $resolved['shell_mode']);
        $this->assertSame('portrait', $resolved['orientation_policy']);
        $this->assertTrue($resolved['show_signoff_footer']);
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

        Permission::query()->updateOrCreate(
            ['name' => 'qmh.approve.attest'],
            [
                'display_name' => 'Attestation Fallback Approve Quality Management Hub',
                'module' => 'qmh',
                'action' => 'approve-attest',
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

    /**
     * @return array{0: QmhDocumentRevision, 1: User}
     */
    private function createFrRevisionInApprovalWithSourcePdfMetadata(): array
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-'.str((string) now()->unix())->append((string) random_int(100, 999)),
            'title' => 'Dokumen Uji FR Runtime Fallback',
            'clause' => 4,
            'doc_type' => 'formulir',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'in_approval',
            'content_html' => '<h1>Dokumen FR</h1><p>Konten fallback.</p>',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'submitted_at' => now()->subHours(2),
            'reviewed_at' => now()->subHour(),
            'source_pdf_disk' => 'local',
            'source_pdf_path' => 'qmh/source/missing-master.pdf',
        ]);

        $document->current_revision_id = $revision->id;
        $document->save();

        return [$revision, $approver];
    }

    /**
     * @return array{0: QmhDocumentRevision, 1: User}
     */
    private function createFrRevisionInApprovalWithRealSourcePdf(): array
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-FR-REAL-'.str((string) now()->unix())->append((string) random_int(100, 999)),
            'title' => 'Dokumen Uji FR Source PDF Valid',
            'clause' => 4,
            'doc_type' => 'formulir',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $sourcePdfBinary = $this->minimalPdfBinary();
        $sourcePdfPath = 'qmh/source/test-real-master.pdf';
        Storage::disk('local')->put($sourcePdfPath, $sourcePdfBinary);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'in_approval',
            'content_html' => '<h1>Dokumen FR</h1><p>Konten fallback.</p>',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'submitted_at' => now()->subHours(2),
            'reviewed_at' => now()->subHour(),
            'source_pdf_disk' => 'local',
            'source_pdf_path' => $sourcePdfPath,
            'source_pdf_sha256' => hash('sha256', $sourcePdfBinary),
        ]);

        $document->current_revision_id = $revision->id;
        $document->save();

        return [$revision, $approver];
    }

    /**
     * @return array{0: QmhDocumentRevision, 1: User}
     */
    private function createIkRevisionInApproval(): array
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-IK-'.str((string) now()->unix())->append((string) random_int(100, 999)),
            'title' => 'Dokumen Uji IK',
            'clause' => 7,
            'doc_type' => 'ik',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'in_approval',
            'content_html' => '<h1>Dokumen IK</h1><p>Konten instruksi kerja.</p>',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'submitted_at' => now()->subHours(2),
            'reviewed_at' => now()->subHour(),
        ]);

        $document->current_revision_id = $revision->id;
        $document->save();

        return [$revision, $approver];
    }

    private function minimalPdfBinary(): string
    {
        /** @var class-string $fpdfClass */
        $fpdfClass = '\\FPDF';
        $pdf = new $fpdfClass;
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'FORM SUMBER FR-V2', 0, 1, 'C');

        return $pdf->Output('S');
    }

    private function grantAttestationPermissionToAdminRole(): void
    {
        $permission = Permission::query()->where('name', 'qmh.approve.attest')->firstOrFail();

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $permission->id,
        ]);
    }
}
