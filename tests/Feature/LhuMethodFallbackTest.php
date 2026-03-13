<?php

namespace Tests\Feature;

use App\Enums\TestProcessStage;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LhuMethodFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_lhu_uses_sample_test_methods_when_instrument_not_provided(): void
    {
        Storage::fake('public');

        $this->mock(\App\Services\PdfRenderService::class, function ($mock) {
            $mock->shouldReceive('htmlToPdf')
                ->andReturnUsing(fn (string $html) => $html);
        });

        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'analis',
        ]);

        $testRequest = TestRequest::factory()->create();
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'test_methods' => json_encode(['gc_ms', 'uv_vis']),
        ]);

        $process = SampleTestProcess::factory()->create([
            'sample_id' => $sample->id,
            'stage' => TestProcessStage::INTERPRETATION,
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->put(route('testing.processes.update', $process), [
                'sample_id' => $sample->id,
                'stage' => TestProcessStage::INTERPRETATION->value,
                'performed_by' => $user->id,
                'test_result' => 'positive',
                'detected_substance' => 'Metamfetamina',
                'completed_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $document = \App\Models\Document::query()
            ->where('test_request_id', $testRequest->id)
            ->where('sample_id', $sample->id)
            ->where('document_type', 'laporan_hasil_uji')
            ->latest()
            ->first();

        $this->assertNotNull($document);

        $html = Storage::disk($document->storage_disk ?? 'public')->get($document->path);

        $this->assertStringContainsString('<th class="c3">Metode Uji</th>', $html);
        $this->assertStringContainsString('GC-MS (Gas Chromatography–Mass Spectrometry)', $html);
        $this->assertStringContainsString('UV-VIS (Ultraviolet–Visible Spectrophotometry)', $html);
    }
}
