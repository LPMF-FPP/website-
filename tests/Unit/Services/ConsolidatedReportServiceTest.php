<?php

namespace Tests\Unit\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\ConsolidatedReport;
use App\Repositories\SettingsRepository;
use App\Services\ConsolidatedReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ConsolidatedReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_signers_structure_constant_exists()
    {
        $this->assertTrue(defined(ConsolidatedReportService::class.'::DEFAULT_SIGNERS_STRUCTURE'));

        $structure = ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE;
        $this->assertIsArray($structure);
        $this->assertCount(3, $structure);
        $this->assertEquals('Pembuat', $structure[0]['role']);
        $this->assertEquals('Pemeriksa', $structure[1]['role']);
        $this->assertEquals('Pengesah', $structure[2]['role']);
    }

    public function test_get_default_signers_returns_constant_when_settings_empty()
    {
        $mockSettings = Mockery::mock(SettingsRepository::class);
        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.default_signers', null)
            ->andReturn(null);

        $service = new ConsolidatedReportService(
            $this->createMock(\App\Services\ActiveSubstanceService::class),
            $this->createMock(\App\Services\IkuService::class),
            $mockSettings
        );

        $signers = $service->getDefaultSigners();

        $this->assertEquals(ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE, $signers);
    }

    public function test_send_generation_notification_disabled()
    {
        Bus::fake();

        $mockSettings = Mockery::mock(SettingsRepository::class);
        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.notify_on_generate', true)
            ->andReturn(false);

        $service = new ConsolidatedReportService(
            $this->createMock(\App\Services\ActiveSubstanceService::class),
            $this->createMock(\App\Services\IkuService::class),
            $mockSettings
        );

        // Dummy report (no DB needed since we mock settings and return early)
        $report = new ConsolidatedReport;

        $result = $service->sendGenerationNotification($report);

        $this->assertEquals(0, $result);
        Bus::assertNotDispatched(SendWhatsAppMessage::class);
    }

    public function test_send_generation_notification_success()
    {
        Bus::fake();

        $mockSettings = Mockery::mock(SettingsRepository::class);
        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.notify_on_generate', true)
            ->andReturn(true);

        $mockSettings->shouldReceive('get')
            ->with('consolidated_report.notify_phone')
            ->andReturn('+62812345678');

        $service = new ConsolidatedReportService(
            $this->createMock(\App\Services\ActiveSubstanceService::class),
            $this->createMock(\App\Services\IkuService::class),
            $mockSettings
        );

        // Create a real report because we need ID for Batch source_id
        // But since we use RefreshDatabase, we can create it
        // However, we need to bypass foreign keys or create dependencies if ConsolidatedReport has any
        // ConsolidatedReport seems independent enough based on migration usually
        // Let's force create one
        $report = new ConsolidatedReport([
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_label' => 'Bulan Ini',
            'generated_at' => now(),
            'report_data' => ['statistics' => ['total_requests_received' => 10, 'total_samples_received' => 50]],
            'narrative_sections' => [],
            'signers' => [],
            'comparison_data' => [],
        ]);
        $report->save();

        $result = $service->sendGenerationNotification($report);

        $this->assertEquals(1, $result);

        Bus::assertDispatched(SendWhatsAppMessage::class, function ($job) {
            return $job->phone === '+62812345678' &&
                   str_contains($job->message, 'Laporan monthly periode Bulan Ini') &&
                   str_contains($job->message, 'Total Permintaan: 10') &&
                   str_contains($job->message, 'Total Sampel: 50');
        });

        $this->assertDatabaseHas('whatsapp_message_batches', [
            'type' => 'consolidated_report_notification',
            'source_type' => ConsolidatedReport::class,
            'source_id' => $report->id,
            'total_recipients' => 1,
        ]);
    }

    public function test_should_auto_generate_biweekly_1_15_on_16th(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 16, 6, 0, 0, 'Asia/Jakarta'));

        $service = app(ConsolidatedReportService::class);
        $reports = $service->shouldAutoGenerate();

        $this->assertCount(1, $reports);
        $this->assertEquals('biweekly', $reports[0]['type']);
        $this->assertEquals(1, $reports[0]['start']->day);
        $this->assertEquals(15, $reports[0]['end']->day);
    }

    public function test_should_not_auto_generate_biweekly_16_31_on_1st(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 1, 6, 0, 0, 'Asia/Jakarta'));

        $service = app(ConsolidatedReportService::class);
        $reports = $service->shouldAutoGenerate();

        // Should only have monthly, NOT biweekly for 16-31
        $biweeklyReports = array_filter($reports, fn ($r) => $r['type'] === 'biweekly');
        $this->assertEmpty($biweeklyReports);

        // Monthly should exist
        $monthlyReports = array_filter($reports, fn ($r) => $r['type'] === 'monthly');
        $this->assertCount(1, $monthlyReports);
    }

    public function test_should_auto_generate_monthly_on_1st(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 1, 6, 0, 0, 'Asia/Jakarta'));

        $service = app(ConsolidatedReportService::class);
        $reports = $service->shouldAutoGenerate();

        $monthlyReports = array_filter($reports, fn ($r) => $r['type'] === 'monthly');
        $this->assertCount(1, $monthlyReports);
    }
}
