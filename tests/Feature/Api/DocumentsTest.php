<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $investigatorUser;
    protected Investigator $investigator;
    protected TestRequest $testRequest;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        $this->investigator = Investigator::factory()->create();
        $this->investigatorUser = User::factory()->create([
            'role' => 'investigator',
            'investigator_id' => $this->investigator->id,
        ]);
        
        $this->testRequest = TestRequest::factory()->create([
            'investigator_id' => $this->investigator->id,
        ]);
    }

    public function test_can_list_request_documents(): void
    {
        // Create some documents for this request
        Document::factory()->count(3)->create([
            'investigator_id' => $this->investigator->id,
            'test_request_id' => $this->testRequest->id,
            'source' => 'upload',
        ]);

        // Create document for different request (should not appear)
        Document::factory()->create([
            'investigator_id' => $this->investigator->id,
            'test_request_id' => null,
        ]);

        $this->actingAs($this->investigatorUser);

        $response = $this->getJson("/api/requests/{$this->testRequest->id}/documents");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'mime',
                        'source',
                        'preview_url',
                        'download_url',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_lists_only_authorized_documents(): void
    {
        // Create documents with different visibility
        $doc1 = Document::factory()->create([
            'investigator_id' => $this->investigator->id,
            'test_request_id' => $this->testRequest->id,
        ]);

        $this->actingAs($this->investigatorUser);

        $response = $this->getJson("/api/requests/{$this->testRequest->id}/documents");

        $response->assertOk();
        
        // Should only see authorized documents
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($doc1->id, $ids);
    }

    public function test_can_delete_own_document(): void
    {
        $document = Document::factory()->create([
            'investigator_id' => $this->investigator->id,
            'test_request_id' => $this->testRequest->id,
            'file_path' => 'test/document.pdf',
            'storage_disk' => 'public',
        ]);

        // Create fake file
        Storage::disk('public')->put($document->file_path, 'fake content');

        $this->actingAs($this->investigatorUser);

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('id', $document->id);

        // Document should be soft deleted from database
        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);

        // File should be deleted from storage
        Storage::disk('public')->assertMissing($document->file_path);
    }

    public function test_admin_can_delete_any_document(): void
    {
        $document = Document::factory()->create([
            'investigator_id' => $this->investigator->id,
            'test_request_id' => $this->testRequest->id,
            'file_path' => 'test/admin-delete.pdf',
            'storage_disk' => 'public',
        ]);

        Storage::disk('public')->put($document->file_path, 'fake content');

        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertOk();

        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_cannot_delete_unauthorized_document(): void
    {
        $otherInvestigator = Investigator::factory()->create();
        $document = Document::factory()->create([
            'investigator_id' => $otherInvestigator->id,
        ]);

        $this->actingAs($this->investigatorUser);

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertForbidden();

        // Document should still exist
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_delete_logs_audit_trail(): void
    {
        $document = Document::factory()->create([
            'investigator_id' => $this->investigator->id,
            'test_request_id' => $this->testRequest->id,
            'original_filename' => 'important-document.pdf',
            'document_type' => 'request_letter',
        ]);

        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertOk();

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE_DOCUMENT_API',
            'target' => (string) $document->id,
        ]);
    }

    public function test_list_documents_requires_authentication(): void
    {
        $response = $this->getJson("/api/requests/{$this->testRequest->id}/documents");

        $response->assertUnauthorized();
    }

    public function test_list_documents_requires_authorization(): void
    {
        // Create user with investigator role but different investigator_id
        $otherInvestigator = Investigator::factory()->create();
        $otherUser = User::factory()->create([
            'role' => 'investigator',
            'investigator_id' => $otherInvestigator->id,
        ]);
        $this->actingAs($otherUser);

        $response = $this->getJson("/api/requests/{$this->testRequest->id}/documents");

        $response->assertForbidden();
    }

    public function test_delete_document_requires_authentication(): void
    {
        $document = Document::factory()->create();

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertUnauthorized();
    }

    public function test_delete_handles_missing_file_gracefully(): void
    {
        $document = Document::factory()->create([
            'investigator_id' => $this->investigator->id,
            'file_path' => 'missing/file.pdf',
            'storage_disk' => 'public',
        ]);

        $this->actingAs($this->admin);

        $response = $this->deleteJson("/api/documents/{$document->id}");

        $response->assertOk();

        // Document should be soft-deleted (deleted_at should be set)
        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_returns_temporary_signed_download_urls(): void
    {
        $document = Document::factory()->create([
            'investigator_id' => $this->investigator->id,
            'test_request_id' => $this->testRequest->id,
        ]);

        $this->actingAs($this->investigatorUser);

        $response = $this->getJson("/api/requests/{$this->testRequest->id}/documents");

        $response->assertOk();

        $doc = collect($response->json('data'))->firstWhere('id', $document->id);
        
        $this->assertNotNull($doc);
        $this->assertStringContainsString('signature=', $doc['download_url']);
        $this->assertStringContainsString('expires=', $doc['download_url']);
    }
}
