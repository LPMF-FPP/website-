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
}
