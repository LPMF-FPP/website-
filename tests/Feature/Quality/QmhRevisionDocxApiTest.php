<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhRevisionDocxApiTest extends TestCase
{
    use RefreshDatabase;

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
        Storage::fake('local');
    }

    public function test_docx_save_requires_active_lock_owner(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-DOCX-1',
            'title' => 'Dokumen Uji DOCX',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'dibuat_oleh' => $user->id,
            'source_docx_path' => 'qmh/QMH-DOCX-1/E1-R0/source.docx',
            'source_docx_version' => 1,
            'export_pdf_from_docx' => false,
        ]);

        Storage::disk('local')->put($revision->source_docx_path, 'fake-docx');

        $file = UploadedFile::fake()->create('source.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($user)
            ->put("/api/quality/revisions/{$revision->id}/docx", [
                'file' => $file,
            ])
            ->assertStatus(409);
    }

    public function test_docx_save_updates_revision_and_enables_docx_pdf_export(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-DOCX-2',
            'title' => 'Dokumen Uji DOCX Save',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'dibuat_oleh' => $user->id,
            'source_docx_path' => 'qmh/QMH-DOCX-2/E1-R0/source.docx',
            'source_docx_version' => 1,
            'export_pdf_from_docx' => false,
        ]);

        Storage::disk('local')->put($revision->source_docx_path, 'fake-docx');

        $this->actingAs($user)
            ->postJson("/api/quality/revisions/{$revision->id}/lock", [])
            ->assertOk();

        $file = UploadedFile::fake()->create('source.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($user)
            ->put("/api/quality/revisions/{$revision->id}/docx", [
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('data.export_pdf_from_docx', true);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'export_pdf_from_docx' => true,
        ]);
    }

    public function test_docx_save_accepts_raw_binary_body(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-DOCX-RAW-1',
            'title' => 'Dokumen Uji DOCX Raw Body',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'dibuat_oleh' => $user->id,
            'source_docx_path' => 'qmh/QMH-DOCX-RAW-1/E1-R0/source.docx',
            'source_docx_version' => 1,
            'export_pdf_from_docx' => false,
        ]);

        Storage::disk('local')->put($revision->source_docx_path, 'fake-docx');

        $this->actingAs($user)
            ->postJson("/api/quality/revisions/{$revision->id}/lock", [])
            ->assertOk();

        $binary = 'raw-docx-bytes';
        $expectedHash = hash('sha256', $binary);

        $response = $this->actingAs($user)->call(
            'PUT',
            "/api/quality/revisions/{$revision->id}/docx",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => self::DOCX_MIME,
                'HTTP_ACCEPT' => 'application/json',
            ],
            $binary
        );

        $response->assertOk()
            ->assertJsonPath('data.export_pdf_from_docx', true)
            ->assertJsonPath('data.source_docx_checksum', $expectedHash);

        $this->assertSame($binary, Storage::disk('local')->get($revision->source_docx_path));
        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'export_pdf_from_docx' => true,
            'source_docx_checksum' => $expectedHash,
        ]);
    }

    public function test_docx_download_requires_active_lock_owner(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-DOCX-DOWN-1',
            'title' => 'Dokumen Uji DOCX Download',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'dibuat_oleh' => $user->id,
            'source_docx_path' => 'qmh/QMH-DOCX-DOWN-1/E1-R0/source.docx',
            'source_docx_version' => 1,
            'export_pdf_from_docx' => false,
        ]);

        Storage::disk('local')->put($revision->source_docx_path, 'fake-docx');

        $this->actingAs($user)
            ->get("/api/quality/revisions/{$revision->id}/docx")
            ->assertStatus(409);

        $this->actingAs($other)
            ->postJson("/api/quality/revisions/{$revision->id}/lock", [])
            ->assertOk();

        $this->actingAs($user)
            ->get("/api/quality/revisions/{$revision->id}/docx")
            ->assertStatus(403);
    }

    public function test_docx_download_returns_docx_for_lock_owner(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-DOCX-DOWN-2',
            'title' => 'Dokumen Uji DOCX Download Owner',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'dibuat_oleh' => $user->id,
            'source_docx_path' => 'qmh/QMH-DOCX-DOWN-2/E1-R0/source.docx',
            'source_docx_version' => 1,
            'export_pdf_from_docx' => false,
        ]);

        $expectedBinary = 'fake-docx-binary';
        Storage::disk('local')->put($revision->source_docx_path, $expectedBinary);

        $this->actingAs($user)
            ->postJson("/api/quality/revisions/{$revision->id}/lock", [])
            ->assertOk();

        $response = $this->actingAs($user)->get("/api/quality/revisions/{$revision->id}/docx");
        $response->assertOk();

        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString(self::DOCX_MIME, $contentType);
        $this->assertSame($expectedBinary, $response->streamedContent());
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

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $viewPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);
    }
}
