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

class QmhPendukungWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createQmhPermissions();
        Storage::fake('local');
    }

    public function test_admin_can_create_pendukung_document_via_web(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->post('/quality/pendukung', [
                'doc_code' => 'DP-4.001',
                'title' => 'Diagram Alur Penerimaan',
                'clause' => 4,
                'file' => UploadedFile::fake()->createWithContent('diagram.pdf', "%PDF-1.4\nPendukung"),
            ]);

        $document = QmhDocument::query()->where('doc_code', 'DP-4.001')->first();

        $this->assertNotNull($document);
        $response->assertRedirect(route('quality.pendukung.show', $document));

        $this->assertDatabaseHas('qmh_documents', [
            'doc_code' => 'DP-4.001',
            'doc_type' => 'pendukung',
            'clause' => 4,
        ]);

        $revision = QmhDocumentRevision::query()->where('document_id', $document?->id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('v1', $revision?->version_label);
        $this->assertNotNull($revision?->file_hash);
        $this->assertTrue(Storage::disk('local')->exists((string) $revision?->source_pdf_path));
    }

    public function test_showing_sop_document_on_pendukung_route_returns_not_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-404',
            'title' => 'SOP Regular',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/quality/pendukung/'.$document->id)
            ->assertNotFound();
    }

    public function test_download_after_version_update_returns_latest_uploaded_file_without_cache(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/quality/pendukung', [
                'doc_code' => 'DP-4.099',
                'title' => 'Dokumen Pendukung Cache',
                'clause' => 4,
                'file' => UploadedFile::fake()->createWithContent('awal.pdf', "%PDF-1.4\nfile-awal"),
            ])
            ->assertRedirect();

        $document = QmhDocument::query()->where('doc_code', 'DP-4.099')->firstOrFail();

        $this->actingAs($user)
            ->patch('/quality/pendukung/'.$document->id, [
                'doc_code' => 'DP-4.099',
                'title' => 'Dokumen Pendukung Cache Revisi',
                'clause' => 4,
                'file' => UploadedFile::fake()->createWithContent('revisi.pdf', "%PDF-1.4\nfile-revisi"),
            ])
            ->assertRedirect(route('quality.pendukung.show', $document));

        $response = $this->actingAs($user)
            ->get(route('quality.pendukung.file', ['document' => $document, 'download' => 1]));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $this->assertSame("%PDF-1.4\nfile-revisi", $response->streamedContent());

        $document->refresh();
        $this->actingAs($user)
            ->get(route('quality.pendukung.show', $document))
            ->assertOk()
            ->assertSee('v='.(string) $document->current_revision_id, false);
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
