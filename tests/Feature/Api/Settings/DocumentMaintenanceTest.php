<?php

namespace Tests\Feature\Api\Settings;

use App\Models\Document;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMaintenanceTest extends TestCase
{
    use RefreshDatabase;

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
}
