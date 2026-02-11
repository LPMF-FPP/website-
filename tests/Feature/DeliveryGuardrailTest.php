<?php

namespace Tests\Feature;

use App\Enums\SampleStatus;
use App\Enums\TestProcessStage;
use App\Models\Document;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryGuardrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_mark_ready_for_delivery_without_lhu()
    {
        // Setup user
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        // Setup sample and request
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_DONE,
        ]);

        // Create completed processes for all stages
        SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::PREPARATION,
            'completed_at' => now(),
        ]);
        SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INSTRUMENTATION,
            'completed_at' => now(),
        ]);
        SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'completed_at' => now(),
        ]);

        // Ensure NO LHU document exists
        $this->assertDatabaseMissing('documents', [
            'test_request_id' => $testRequest->id,
            'sample_id' => $sample->id,
            'document_type' => 'laporan_hasil_uji',
        ]);

        // Act: Try to mark as ready for delivery
        $response = $this->actingAs($user)
            ->post(route('samples.ready-for-delivery', $sample));

        // Assert: Should redirect back with errors
        $response->assertSessionHasErrors(['error']);

        // Assert: Status should NOT change
        $sample->refresh();
        $this->assertNotEquals(SampleStatus::READY_FOR_DELIVERY->value, $sample->status);
    }

    public function test_can_mark_ready_for_delivery_with_lhu()
    {
        // Setup user
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        // Setup sample and request
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_DONE,
        ]);

        // Create completed processes for all stages
        SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::PREPARATION,
            'completed_at' => now(),
        ]);
        SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INSTRUMENTATION,
            'completed_at' => now(),
        ]);
        SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'completed_at' => now(),
        ]);

        // Create LHU document
        Document::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_id' => $sample->id,
            'document_type' => 'laporan_hasil_uji',
        ]);

        // Act: Try to mark as ready for delivery
        $response = $this->actingAs($user)
            ->post(route('samples.ready-for-delivery', $sample));

        // Assert: Should redirect to delivery show page (success)
        $response->assertRedirect(route('delivery.show', $testRequest));

        // Assert: Status SHOULD change
        $sample->refresh();
        $this->assertEquals(SampleStatus::READY_FOR_DELIVERY->value, $sample->status->value ?? $sample->status);
    }
}
