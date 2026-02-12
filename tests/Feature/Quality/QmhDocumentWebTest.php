<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhDocumentWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->createQmhPermissions();
    }

    public function test_admin_can_access_quality_pages(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/quality')
            ->assertOk()
            ->assertViewIs('quality.index');

        $this->actingAs($user)
            ->get('/quality/documents')
            ->assertOk()
            ->assertViewIs('quality.index');

        $this->actingAs($user)
            ->get('/quality/documents/create')
            ->assertOk()
            ->assertViewIs('quality.create')
            ->assertSee('Buat Dokumen')
            ->assertSee('Simpan Dokumen')
            ->assertSee('Kembali');
    }

    public function test_user_without_qmh_permission_is_redirected_from_quality_pages(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'investigator']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/quality')
            ->assertRedirect('/dashboard');
    }

    public function test_can_store_document_from_web_form_and_redirects_to_index(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->post('/quality/documents', [
                'doc_code' => 'QMH-WEB-001',
                'title' => 'Dokumen dari Form Web',
                'clause' => 5,
                'doc_type' => 'ik',
                'change_summary' => 'create from web',
            ]);

        $response->assertRedirect(route('quality.documents.index'));

        $this->assertDatabaseHas('qmh_documents', [
            'doc_code' => 'QMH-WEB-001',
            'title' => 'Dokumen dari Form Web',
            'clause' => 5,
            'doc_type' => 'ik',
        ]);
    }

    public function test_web_index_supports_search_and_filter_query_params(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $docOne = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-100',
            'title' => 'SOP Umum',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revisionOne = QmhDocumentRevision::query()->create([
            'document_id' => $docOne->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $docOne->update(['current_revision_id' => $revisionOne->id]);

        $docTwo = QmhDocument::query()->create([
            'doc_code' => 'QMH-IK-200',
            'title' => 'IK Kalibrasi Neraca',
            'clause' => 6,
            'doc_type' => 'ik',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revisionTwo = QmhDocumentRevision::query()->create([
            'document_id' => $docTwo->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $docTwo->update(['current_revision_id' => $revisionTwo->id]);

        $response = $this->actingAs($user)
            ->get('/quality/documents?search=QMH&clause=6&doc_type=ik&status=draft&edition_number=1&revision_number=0');

        $response->assertOk();
        $response->assertSee('Dashboard QMH');
        $response->assertSee('Lihat');
        $response->assertSee('QMH-IK-200');
        $response->assertDontSee('QMH-SOP-100');
    }

    public function test_quality_landing_shows_summary_cards(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-300',
            'title' => 'SOP Validasi',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $currentRevision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'version_label' => 'E1-R2',
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $document->update(['current_revision_id' => $currentRevision->id]);

        QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 1,
            'version_label' => 'E1-R1',
            'status' => 'obsolete',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
            'obsolete_at' => now()->subDay(),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $currentRevision->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'copy_type' => 'controlled',
            'downloaded_by' => $user->id,
            'downloaded_at' => now(),
            'watermark_text' => 'CONTROLLED COPY',
            'file_hash' => str_repeat('a', 64),
        ]);

        QmhDocumentDownloadLog::query()->create([
            'document_id' => $document->id,
            'revision_id' => $currentRevision->id,
            'edition_number' => 1,
            'revision_number' => 2,
            'copy_type' => 'uncontrolled',
            'downloaded_by' => $user->id,
            'downloaded_at' => now(),
            'reason' => 'referensi',
            'watermark_text' => 'UNCONTROLLED COPY',
            'file_hash' => str_repeat('b', 64),
        ]);

        $response = $this->actingAs($user)->get('/quality');

        $response->assertOk();
        $response->assertSee('Total Dokumen');
        $response->assertSee('Dokumen Published');
        $response->assertSee('Dokumen In Review');
        $response->assertSee('Revisi Obsolete');
        $response->assertSee('Unduhan Controlled');
        $response->assertSee('Unduhan Uncontrolled');
        $response->assertSee('Semua Klausul');
        $response->assertSee('Semua Jenis');
    }

    public function test_user_with_qmh_view_permission_can_access_document_show_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-400',
            'title' => 'SOP Show Page',
            'clause' => 5,
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
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $user->id,
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->actingAs($user)
            ->get('/quality/documents/'.$document->id)
            ->assertOk()
            ->assertViewIs('quality.show')
            ->assertSee('QMH-SOP-400');
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
