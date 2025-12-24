<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createDocument(User $creator, array $attributes = []): Document
    {
        $investigator = Investigator::factory()->create();
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $creator->id,
        ]);

        return Document::factory()->create(array_merge([
            'investigator_id' => $investigator->id,
            'test_request_id' => $testRequest->id,
        ], $attributes));
    }

    public function test_admin_can_delete_document_and_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $document = $this->createDocument($admin, [
            'source' => 'upload',
            'file_path' => 'investigators/example/upload.pdf',
            'storage_disk' => 'public',
        ]);

        Storage::disk('public')->put($document->file_path, 'file');

        $response = $this->actingAs($admin)->deleteJson("/api/documents/{$document->id}");

        $response->assertOk()
            ->assertJson(['deleted' => true, 'id' => $document->id]);

        Storage::disk('public')->assertMissing($document->file_path);
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_analyst_cannot_delete_generated_document(): void
    {
        Storage::fake('public');
        $analyst = User::factory()->create(['role' => 'analyst']);

        $document = $this->createDocument($analyst, [
            'source' => 'generated',
            'file_path' => 'investigators/example/generated.pdf',
            'storage_disk' => 'public',
        ]);

        Storage::disk('public')->put($document->file_path, 'file');

        $this->actingAs($analyst)->deleteJson("/api/documents/{$document->id}")
            ->assertStatus(403);

        Storage::disk('public')->assertExists($document->file_path);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }
}
