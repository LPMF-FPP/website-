<?php

namespace Tests\Feature\Console\Commands;

use App\Models\ConsolidatedReport;
use App\Models\SystemSetting;
use App\Services\ConsolidatedReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GenerateConsolidatedReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure system settings exist for the test
        SystemSetting::create(['key' => 'consolidated_report.auto_generate_enabled', 'value' => '1']);
    }

    public function test_it_generates_report_and_uses_service_for_notification()
    {
        // Mock the service
        $mockService = Mockery::mock(ConsolidatedReportService::class);
        $this->app->instance(ConsolidatedReportService::class, $mockService);

        // Setup mock expectations
        $mockService->shouldReceive('shouldAutoGenerate')
            ->once()
            ->andReturn([
                [
                    'type' => 'monthly',
                    'start' => Carbon::now()->startOfMonth(),
                    'end' => Carbon::now()->endOfMonth(),
                ],
            ]);

        $mockService->shouldReceive('getDefaultNarratives')->andReturn(['header' => 'test']);
        $mockService->shouldReceive('getDefaultSigners')->andReturn([]);

        // Use a real partial mock or a more robust mock for the report
        // The issue might be that $report->id is accessed, or update() is called
        // Since we return a mock object from generate(), we need to support update() call

        $mockReport = Mockery::mock(ConsolidatedReport::class)->makePartial();
        $mockReport->id = 1;
        $mockReport->period_type = 'monthly';
        $mockReport->shouldReceive('update')->with(['is_auto_generated' => true])->once();

        $mockService->shouldReceive('generate')
            ->once()
            ->andReturn($mockReport);

        // This is the CRITICAL expectation - verify we call the service method
        $mockService->shouldReceive('sendGenerationNotification')
            ->once()
            ->with($mockReport)
            ->andReturn(1); // Simulate 1 admin notified

        // Run command
        $this->artisan('reports:generate-consolidated')
            ->expectsOutput('Checking for scheduled reports...')
            ->expectsOutput('Generating monthly report...')
            ->expectsOutput('Report generated successfully: ID 1')
            ->expectsOutput('Notification dispatched to admin.')
            ->assertSuccessful();
    }
}
