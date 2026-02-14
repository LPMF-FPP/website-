<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Quality\QmhDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhOfficeEditorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('quality.office.editor_url', 'https://office.test.local/editor');
        config()->set('quality.office.jwt_secret', 'qmh-office-test-secret');
        config()->set('quality.office.callback_hosts', ['office.test.local']);

        $this->createQmhPermissions();
    }

    public function test_office_session_requires_active_lock_owner(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/office-session")
            ->assertForbidden();

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $response = $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/office-session");

        $response->assertOk();
        $response->assertJsonPath('data.revision_id', $revision->id);
        $response->assertJsonPath('data.config.editorConfig.callbackUrl', url("/api/quality/revisions/{$revision->id}/office-callback"));
        $response->assertJsonPath('data.config.document.fileType', 'docx');
        $response->assertJsonStructure([
            'data' => [
                'revision_id',
                'token',
                'config',
            ],
        ]);
    }

    public function test_office_callback_rejects_invalid_signature_or_untrusted_host(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->postJson("/api/quality/revisions/{$revision->id}/office-callback", [
            'status' => 2,
            'token' => 'invalid-token',
        ], [
            'X-Office-Callback-Host' => 'office.test.local',
        ])->assertStatus(401);

        $validToken = $this->issueCallbackToken($revision->id);

        $this->postJson("/api/quality/revisions/{$revision->id}/office-callback", [
            'status' => 2,
            'token' => $validToken,
        ], [
            'X-Office-Callback-Host' => 'malicious.local',
        ])->assertStatus(403);
    }

    public function test_office_callback_updates_revision_autosave_metadata(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $response = $this->postJson("/api/quality/revisions/{$revision->id}/office-callback", [
            'status' => 2,
            'content_html' => '<p>Konten autosave Office</p>',
            'token' => $this->issueCallbackToken($revision->id),
        ], [
            'X-Office-Callback-Host' => 'office.test.local',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'content_html' => '<p>Konten autosave Office</p>',
            'source_docx_version' => 2,
        ]);

        $this->assertNotNull(QmhDocumentRevision::query()->findOrFail($revision->id)->last_autosaved_at);
    }

    private function createDraftRevision(User $creator): QmhDocumentRevision
    {
        Storage::fake('local');
        Storage::disk('local')->put('templates/qmh/sop-editor.docx', 'office-seed-docx');

        $template = QmhTemplate::query()->create([
            'name' => 'Template Editor SOP',
            'clause' => 8,
            'doc_type' => 'sop',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'templates/qmh/sop-editor.docx',
            'is_active' => true,
        ]);

        $document = app(QmhDocumentService::class)->createDraft([
            'doc_code' => 'QMH-OFFICE-'.random_int(1000, 9999),
            'title' => 'Draft Office Editor',
            'clause' => 8,
            'doc_type' => 'sop',
            'template_id' => $template->id,
        ], $creator->id);

        return $document->currentRevision;
    }

    private function issueCallbackToken(int $revisionId): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');

        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => 'qmh-office',
            'revision_id' => $revisionId,
            'iat' => time(),
            'exp' => time() + 300,
        ])), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', $header.'.'.$payload, (string) config('quality.office.jwt_secret'), true);

        return $header.'.'.$payload.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
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
