<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMaintenanceDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Define manage-settings gate
        \Illuminate\Support\Facades\Gate::define('manage-settings', fn () => true);
    }

    /** @test */
    public function it_can_cleanup_duplicate_documents_with_identical_timestamps()
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();
        $now = now();

        // Create 3 duplicate documents with IDENTICAL created_at
        $docs = Document::factory()->count(3)->create([
            'test_request_id' => $request->id,
            'document_type' => 'laporan_hasil_uji',
            'source' => 'generated',
            'created_at' => $now,
            'updated_at' => $now,
            'file_path' => 'generated/lhu/doc.pdf', // Dummy path
        ]);

        // Verify we have 3 documents
        $this->assertEquals(3, Document::count());

        // Call cleanup API
        $response = $this->actingAs($user)
            ->postJson('/api/settings/documents/cleanup-duplicates');

        $response->assertStatus(200);

        // Should have deleted 2, kept 1
        $this->assertEquals(1, Document::count());

        // The one remaining should be the one with highest ID (last created)
        $remaining = Document::first();
        $this->assertEquals($docs->last()->id, $remaining->id);

        $response->assertJson([
            'success' => true,
            'deleted' => 2,
        ]);
    }
}
