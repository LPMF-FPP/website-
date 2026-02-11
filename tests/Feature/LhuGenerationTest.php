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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LhuGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lhu_generated_automatically_on_interpretation_complete()
    {
        Storage::fake('public');

        // Setup user
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        // Setup sample and request
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);

        // Create interpretation process
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        // Mock DocumentService to ensure createLhuDocument is called and succeeds
        // However, since we are testing the full flow including the controller,
        // and the controller resolves services from the container,
        // we should rely on the real services or mock them in the container if needed.
        // For a Feature test, using real services (with faked storage) is better for integration testing.

        // Act: Update with completion data (test_result is positive/negative)
        $response = $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
            ]);

        // Assert response is redirect
        $response->assertRedirect();

        // Assert LHU document exists in database
        $this->assertDatabaseHas('documents', [
            'test_request_id' => $testRequest->id,
            'document_type' => 'laporan_hasil_uji',
        ]);

        // Assert process metadata has lhu_number
        $process->refresh();
        $this->assertNotNull($process->metadata['lhu_number'] ?? null);
    }

    public function test_lhu_generation_fails_gracefully_on_error()
    {
        Storage::fake('public');

        // Setup user
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        // Setup sample and request
        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => SampleStatus::INTERPRETATION_IN_PROGRESS,
        ]);

        // Create interpretation process
        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        // Mock DocumentService to throw an exception
        $this->mock(\App\Services\DocumentService::class, function ($mock) {
            $mock->shouldReceive('storeForSampleProcess')->andThrow(new \Exception('Simulated PDF generation failure'));
        });

        // Act: Update with completion data
        $response = $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'instrument' => 'GC-MS',
                'completed_at' => now()->toDateTimeString(),
            ]);

        // Assert response is still redirect (success) because error is logged but not blocking
        $response->assertRedirect();

        // Assert LHU document does NOT exist (failed)
        $this->assertDatabaseMissing('documents', [
            'test_request_id' => $testRequest->id,
            'document_type' => 'laporan_hasil_uji',
        ]);

        // Ensure the process was still updated despite LHU failure
        $process->refresh();
        $this->assertEquals('Metamfetamina', $process->metadata['detected_substance']);
    }
}
