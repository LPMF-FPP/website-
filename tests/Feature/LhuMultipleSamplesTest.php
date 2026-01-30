<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LhuMultipleSamplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_lhu_for_multiple_samples_does_not_overwrite_documents()
    {
        Storage::fake('local');

        // Setup user and service
        /** @var User $user */
        $user = User::factory()->create();
        // Investigator is not linked to user directly in this schema
        $inv = Investigator::factory()->create();
        $this->actingAs($user);

        /** @var DocumentService */
        $service = app(DocumentService::class);

        // Create TestRequest with 2 Samples
        $request = TestRequest::factory()->create();
        $sample1 = Sample::factory()->create(['test_request_id' => $request->id]);
        $sample2 = Sample::factory()->create(['test_request_id' => $request->id]);

        // Fake PDF content
        $content1 = 'PDF Content for Sample 1';
        $content2 = 'PDF Content for Sample 2';

        // 1. Store LHU for Sample 1
        $service->storeGenerated(
            $content1,
            'pdf',
            $inv,
            $request,
            'laporan_hasil_uji',
            'LHU-Sample-1',
            true, // replaceExisting
            $sample1->id
        );

        // Assert Document 1 exists
        $this->assertDatabaseHas('documents', [
            'test_request_id' => $request->id,
            'sample_id' => $sample1->id,
            'original_filename' => 'LHU-Sample-1.pdf',
        ]);

        // 2. Store LHU for Sample 2
        $service->storeGenerated(
            $content2,
            'pdf',
            $inv,
            $request,
            'laporan_hasil_uji',
            'LHU-Sample-2',
            true, // replaceExisting
            $sample2->id
        );

        // Assert Document 2 exists
        $this->assertDatabaseHas('documents', [
            'test_request_id' => $request->id,
            'sample_id' => $sample2->id,
            'original_filename' => 'LHU-Sample-2.pdf',
        ]);

        // CRITICAL ASSERTION: Both documents should exist
        $count = Document::where('test_request_id', $request->id)
            ->where('document_type', 'laporan_hasil_uji')
            ->count();

        $this->assertEquals(2, $count, 'Should have 2 LHU documents, one for each sample');
    }

    public function test_request_level_document_does_not_overwrite_sample_level_document()
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create();
        $inv = Investigator::factory()->create();
        $this->actingAs($user);

        /** @var DocumentService */
        $service = app(DocumentService::class);

        // Create TestRequest with 1 Sample
        $request = TestRequest::factory()->create();
        $sample = Sample::factory()->create(['test_request_id' => $request->id]);

        // 1. Store LHU for Sample (sample-level doc)
        $service->storeGenerated(
            'LHU PDF Content',
            'pdf',
            $inv,
            $request,
            'laporan_hasil_uji',
            'LHU-Sample',
            true,
            $sample->id
        );

        // 2. Store BA Penerimaan (request-level doc, no sample_id)
        $service->storeGenerated(
            'BA PDF Content',
            'pdf',
            $inv,
            $request,
            'ba_penerimaan',
            'BA-Penerimaan',
            true,
            null // No sample_id
        );

        // 3. Regenerate BA Penerimaan - should NOT overwrite LHU
        $service->storeGenerated(
            'BA PDF Content v2',
            'pdf',
            $inv,
            $request,
            'ba_penerimaan',
            'BA-Penerimaan-v2',
            true,
            null
        );

        // Assert: LHU still exists
        $this->assertDatabaseHas('documents', [
            'test_request_id' => $request->id,
            'sample_id' => $sample->id,
            'document_type' => 'laporan_hasil_uji',
        ]);

        // Assert: Only 1 BA (replaced, not duplicated)
        $baCount = Document::where('test_request_id', $request->id)
            ->where('document_type', 'ba_penerimaan')
            ->count();
        $this->assertEquals(1, $baCount, 'Should have 1 BA document (replaced)');

        // Assert: LHU count unchanged
        $lhuCount = Document::where('test_request_id', $request->id)
            ->where('document_type', 'laporan_hasil_uji')
            ->count();
        $this->assertEquals(1, $lhuCount, 'LHU should not be affected by BA regeneration');
    }
}
