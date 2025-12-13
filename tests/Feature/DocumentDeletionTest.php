<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\TestRequest;
use App\Models\Document;
use App\Models\Investigator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class DocumentDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $testRequest;
    protected $document;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create an investigator
        $investigator = Investigator::create([
            'name' => 'Test Investigator',
            'nrp' => '1234567890',
            'rank' => 'AKP',
            'jurisdiction' => 'Test Jurisdiction',
            'phone' => '08123456789',
        ]);

        // Create a test request
        $this->testRequest = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $this->user->id,
            'suspect_name' => 'Test Suspect',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'case_description' => 'Test case',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Create a fake document
        Storage::fake('documents');
        $filePath = 'receipts/test/sample-receipt.pdf';
        Storage::disk('documents')->put($filePath, 'fake content');

        $this->document = Document::create([
            'test_request_id' => $this->testRequest->id,
            'document_type' => 'sample_receipt',
            'file_path' => $filePath,
            'original_filename' => 'Sample Receipt.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'generated_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_delete_document()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/requests/{$this->testRequest->id}/documents/sample_receipt");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'requestId' => $this->testRequest->id,
                'removed' => 'sample_receipt',
            ]);

        // Verify document is deleted from database
        $this->assertDatabaseMissing('documents', [
            'id' => $this->document->id,
        ]);

        // Verify file is deleted from storage
        Storage::disk('documents')->assertMissing($this->document->file_path);
    }

    /** @test */
    public function unauthenticated_user_cannot_delete_document()
    {
        $response = $this->deleteJson("/requests/{$this->testRequest->id}/documents/sample_receipt");

        $response->assertStatus(401);
    }

    /** @test */
    public function cannot_delete_document_with_invalid_type()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/requests/{$this->testRequest->id}/documents/invalid_type");

        $response->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'message' => 'Tipe dokumen tidak valid.',
            ]);
    }

    /** @test */
    public function returns_404_when_document_not_found()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/requests/{$this->testRequest->id}/documents/handover_report");

        $response->assertStatus(404)
            ->assertJson([
                'ok' => false,
                'message' => 'Dokumen tidak ditemukan.',
            ]);
    }

    /** @test */
    public function can_delete_all_document_types()
    {
        $documentTypes = ['sample_receipt', 'handover_report', 'request_letter_receipt'];
        
        foreach ($documentTypes as $type) {
            $filePath = "receipts/test/{$type}.pdf";
            Storage::disk('documents')->put($filePath, 'fake content');

            $doc = Document::create([
                'test_request_id' => $this->testRequest->id,
                'document_type' => $type,
                'file_path' => $filePath,
                'original_filename' => "{$type}.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'generated_by' => $this->user->id,
            ]);

            $response = $this->actingAs($this->user)
                ->deleteJson("/requests/{$this->testRequest->id}/documents/{$type}");

            $response->assertStatus(200)
                ->assertJson([
                    'ok' => true,
                    'removed' => $type,
                ]);

            $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
            Storage::disk('documents')->assertMissing($filePath);
        }
    }

    /** @test */
    public function deletion_is_idempotent_when_file_already_deleted()
    {
        // Delete the file from storage first
        Storage::disk('documents')->delete($this->document->file_path);

        // Should still succeed even if file doesn't exist
        $response = $this->actingAs($this->user)
            ->deleteJson("/requests/{$this->testRequest->id}/documents/sample_receipt");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);

        // Verify document is deleted from database
        $this->assertDatabaseMissing('documents', [
            'id' => $this->document->id,
        ]);
    }
}
