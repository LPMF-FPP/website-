<?php

namespace Tests\Feature\Api\Settings;

use App\Models\Document;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMaintenanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requires_manage_settings_permission(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        Document::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/settings/documents')
            ->assertForbidden();
    }

    public function test_can_list_storage_files_with_document_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = TestRequest::factory()->create(['request_number' => 'REQ-42']);
        Storage::fake('public');

        $path = 'investigators/foo/sample.pdf';
        Storage::disk('public')->put($path, 'dummy');

        $document = Document::factory()
            ->for($request)
            ->state([
                'investigator_id' => $request->investigator_id,
                'document_type' => 'sample_receipt',
                'source' => 'generated',
                'file_path' => $path,
                'path' => $path,
            ])
            ->create();

        $this->actingAs($admin);

        $this->getJson('/api/settings/documents')
            ->assertOk()
            ->assertJsonPath('data.0.path', $path)
            ->assertJsonPath('data.0.document.id', $document->id)
            ->assertJsonPath('data.0.document.request_number', 'REQ-42')
            ->assertJsonPath('data.0.type', 'sample_receipt')
            ->assertJsonStructure([
                'data' => [
                    [
                        'name',
                        'type_label',
                        'path',
                        'document' => [
                            'id',
                            'request_number',
                        ],
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
    }

    public function test_can_filter_by_request_number_and_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $requestA = TestRequest::factory()->create(['request_number' => 'REQ-123']);
        $requestB = TestRequest::factory()->create(['request_number' => 'REQ-999']);
        Storage::fake('public');

        $matchPath = 'investigators/a/sample.pdf';
        Storage::disk('public')->put($matchPath, 'foo');
        $match = Document::factory()
            ->for($requestA)
            ->state([
                'investigator_id' => $requestA->investigator_id,
                'document_type' => 'sample_receipt',
                'file_path' => $matchPath,
                'path' => $matchPath,
            ])
            ->create();

        $otherPath = 'investigators/b/letter.pdf';
        Storage::disk('public')->put($otherPath, 'bar');
        Document::factory()
            ->for($requestB)
            ->state([
                'investigator_id' => $requestB->investigator_id,
                'document_type' => 'request_letter',
                'file_path' => $otherPath,
                'path' => $otherPath,
            ])
            ->create();

        $this->actingAs($admin);

        $this->getJson('/api/settings/documents?request_number=REQ-123&type=sample_receipt')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document.id', $match->id)
            ->assertJsonPath('data.0.document.request_number', 'REQ-123');
    }

    public function test_can_delete_file_with_document_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');
        $request = TestRequest::factory()->create();
        $path = 'investigators/foo/delete.pdf';
        Storage::disk('public')->put($path, 'delete-me');

        $document = Document::factory()
            ->for($request)
            ->state([
                'investigator_id' => $request->investigator_id,
                'file_path' => $path,
                'path' => $path,
            ])
            ->create();

        $this->actingAs($admin);

        $this->deleteJson('/api/settings/documents', [
            'path' => $path,
            'document_id' => $document->id,
        ])
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('document_removed', true);

        Storage::disk('public')->assertMissing($path);
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_can_delete_orphan_file_from_storage(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');
        $path = 'loose/orphan.txt';
        Storage::disk('public')->put($path, 'orphan');

        $this->actingAs($admin);

        $this->deleteJson('/api/settings/documents', [
            'path' => $path,
        ])
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('document_removed', false);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_returns_json_with_200_on_successful_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');

        $this->actingAs($admin);

        $response = $this->getJson('/api/settings/documents?per_page=25&page=1');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'last_page',
                'total',
            ]);
    }

    public function test_returns_json_error_on_invalid_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $response = $this->getJson('/api/settings/documents?per_page=invalid');

        $response->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_cleanup_stats_detects_filesystem_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');

        // Create duplicate files in same request folder and document type
        $basePath = 'investigators/test-folder/2026-01-03-1234/generated/laporan_hasil_uji';
        Storage::disk('public')->put($basePath.'/20260103100000-file-a.pdf', 'content-a');
        Storage::disk('public')->put($basePath.'/20260103100001-file-b.pdf', 'content-b');

        $this->actingAs($admin);

        $response = $this->getJson('/api/settings/documents/cleanup-stats');

        $response->assertOk()
            ->assertJsonPath('duplicate_documents.count', 1)
            ->assertJsonPath('duplicate_documents.groups', 1);
    }

    public function test_cleanup_duplicates_removes_filesystem_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');

        // Create duplicate files - older file should be deleted
        $basePath = 'investigators/test-folder/2026-01-03-1234/generated/laporan_hasil_uji';
        $oldFile = $basePath.'/20260103100000-file-old.pdf';
        $newFile = $basePath.'/20260103100001-file-new.pdf';
        Storage::disk('public')->put($oldFile, 'old-content');
        Storage::disk('public')->put($newFile, 'new-content');

        $this->actingAs($admin);

        $response = $this->postJson('/api/settings/documents/cleanup-duplicates');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deleted', 1);

        // Old file should be deleted, new file kept
        Storage::disk('public')->assertMissing($oldFile);
        Storage::disk('public')->assertExists($newFile);
    }

    public function test_cleanup_stats_returns_zero_when_no_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');

        // Create unique files in different request folders
        Storage::disk('public')->put('investigators/folder/2026-01-03-1111/generated/lhu/file.pdf', 'a');
        Storage::disk('public')->put('investigators/folder/2026-01-03-2222/generated/lhu/file.pdf', 'b');

        $this->actingAs($admin);

        $response = $this->getJson('/api/settings/documents/cleanup-stats');

        $response->assertOk()
            ->assertJsonPath('duplicate_documents.count', 0)
            ->assertJsonPath('duplicate_documents.groups', 0);
    }

    public function test_cleanup_stats_detects_html_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');

        // Create duplicate HTML files in same request folder
        $basePath = 'investigators/test-folder/2026-01-03-1234/generated/laporan_hasil_uji_html';
        Storage::disk('public')->put($basePath.'/20260103100000-file-a.html', 'content-a');
        Storage::disk('public')->put($basePath.'/20260103100001-file-b.html', 'content-b');

        $this->actingAs($admin);

        $response = $this->getJson('/api/settings/documents/cleanup-stats');

        $response->assertOk()
            ->assertJsonPath('duplicate_documents.count', 1)
            ->assertJsonPath('duplicate_documents.groups', 1);
    }

    public function test_cleanup_orphaned_folders_returns_success_flag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');

        // Create orphaned folder
        Storage::disk('public')->put('investigators/orphan-folder-123/file.pdf', 'content');

        $this->actingAs($admin);

        $response = $this->postJson('/api/settings/documents/cleanup-orphaned');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deleted', 1);

        Storage::disk('public')->assertMissing('investigators/orphan-folder-123/file.pdf');
    }
}
