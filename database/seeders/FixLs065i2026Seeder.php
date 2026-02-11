<?php

namespace Database\Seeders;

use App\Enums\SampleStatus;
use App\Http\Controllers\SampleTestProcessController;
use App\Models\Sample;
use App\Services\DocumentService;
use App\Services\DocumentTemplateService;
use App\Services\NumberingService;
use App\Services\PdfRenderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixLs065i2026Seeder extends Seeder
{
    public function run(): void
    {
        $sampleCode = 'LS065I2026';
        $sample = Sample::where('sample_code', $sampleCode)->first();

        if (! $sample) {
            $this->command->error("Sample {$sampleCode} not found.");

            return;
        }

        $this->command->info("Found sample: {$sample->id}");

        $process = $sample->testProcesses()->where('stage', 'interpretation')->first();

        if (! $process) {
            $this->command->error('Interpretation process not found.');

            return;
        }

        $this->command->info('Generating LHU...');

        $controller = app(SampleTestProcessController::class);

        // We'll wrap this in a transaction just in case
        DB::transaction(function () use ($controller, $process, $sample) {
            // Call generateReport to trigger creation. We don't care about the return value (response).
            // Signature: generateReport(process, docs, numbering, template, pdfRender)
            $controller->generateReport(
                $process,
                app(DocumentService::class),
                app(NumberingService::class),
                app(DocumentTemplateService::class),
                app(PdfRenderService::class)
            );

            $this->command->info('LHU Generated.');

            // 2. Update Status
            $this->command->info('Updating status to ready_for_delivery...');
            $sample->update(['status' => SampleStatus::READY_FOR_DELIVERY]);

            // Also ensure the test request status is updated if all samples are ready
            $testRequest = $sample->testRequest;
            if ($testRequest) {
                $allReady = $testRequest->samples()->where('status', '!=', SampleStatus::READY_FOR_DELIVERY->value)->count() === 0;
                if ($allReady) {
                    // Check if completed_at is already set to avoid overwriting with a new timestamp if running multiple times
                    $updateData = ['status' => 'ready_for_delivery'];
                    if (! $testRequest->completed_at) {
                        $updateData['completed_at'] = now();
                    }

                    $testRequest->update($updateData);
                    $this->command->info('Test Request updated to ready_for_delivery.');
                }
            }
        });

        $this->command->info('Fix complete for LS065I2026.');
    }
}
