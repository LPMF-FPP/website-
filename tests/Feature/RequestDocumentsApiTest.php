<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_documents_list_returns_payload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin']);
        $investigator = Investigator::factory()->create();
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
        ]);

        $document = Document::factory()->create([
            'investigator_id' => $investigator->id,
            'test_request_id' => $testRequest->id,
            'document_type' => 'request_letter',
            'file_path' => 'investigators/'.$investigator->id.'/doc.pdf',
            'storage_disk' => 'public',
        ]);

        Storage::disk('public')->put($document->file_path, 'content');

        $response = $this->actingAs($user)->getJson("/api/requests/{$testRequest->id}/documents");

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'preview_url', 'download_url']]]);
    }
}
