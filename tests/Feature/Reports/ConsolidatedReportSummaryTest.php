<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\ActivityLog;
use App\Models\ConsolidatedReport;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Repositories\SettingsRepository;
use App\Services\ConsolidatedReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ConsolidatedReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        Storage::fake('local');
        $this->targets(['2026' => 200]);
        config(['statistics.targets.monthly_requests' => 32]);
    }

    public function test_distinguishes_received_samples_from_completed_samples_and_uses_the_official_target(): void
    {
        $this->sample(['created_at' => '2026-09-01', 'testing_completed_at' => '2026-10-15']);
        $this->sample(['created_at' => '2026-09-01', 'testing_completed_at' => '2026-09-15']);
        $this->sample(['created_at' => '2026-10-15', 'sample_status' => 'received', 'testing_completed_at' => null]);
        config(['statistics.targets.annual_samples' => 999]);

        $data = $this->summary();
        $this->assertSame('Sampel diterima', $data['rows'][1]['category']);
        $this->assertSame(1, $data['rows'][1]['period_value']);
        $this->assertNull($data['rows'][1]['target']);
        $this->assertSame(1, $data['rows'][2]['period_value']);
        $this->assertSame(2, $data['rows'][2]['year_value']);
        $this->assertSame(200, $data['rows'][2]['target']);
        $this->assertSame('Belum tercapai, kurang 198 sampel.', $data['rows'][2]['status']);
        $this->assertSame('Oktober 2026', $data['rows'][2]['period_label']);
        $this->assertSame('1 Januari sampai 31 Oktober 2026', $data['rows'][2]['year_label']);
        $this->assertSame(10, count($data['sample_chart']['rows']));
        $this->assertSame(2, $data['sample_chart']['rows'][9]['samples']);
        $this->assertSame(200, $data['sample_chart']['rows'][9]['target']);
        $this->assertSame(2, $data['sample_chart']['total']);
    }

    public function test_monthly_request_status_handles_below_exact_and_above_target(): void
    {
        config(['statistics.targets.monthly_requests' => 1]);
        $this->assertSame('Belum tercapai, kurang 1 permintaan.', $this->summary()['rows'][0]['status']);
        TestRequest::factory()->createQuietly(['created_at' => '2026-10-10']);
        $this->assertSame('Target tercapai.', $this->summary()['rows'][0]['status']);
        TestRequest::factory()->createQuietly(['created_at' => '2026-10-20']);
        $this->assertSame('Melebihi target sebanyak 1 permintaan.', $this->summary()['rows'][0]['status']);
    }

    public function test_annual_sample_status_handles_below_exact_and_above_target_without_dividing_by_quarter(): void
    {
        $this->targets(['2026' => 1]);
        $this->assertSame('Belum tercapai, kurang 1 sampel.', $this->summary()['rows'][2]['status']);
        $this->sample();
        $this->assertSame('Target tercapai.', $this->summary()['rows'][2]['status']);
        $this->sample();
        $this->assertSame('Melebihi target sebanyak 1 sampel.', $this->summary()['rows'][2]['status']);
    }

    public function test_biweekly_partial_month_and_quarter_do_not_compare_counts_with_a_monthly_target(): void
    {
        foreach ([['2026-10-01', '2026-10-15'], ['2026-10-05', '2026-10-31'], ['2026-07-01', '2026-09-30']] as [$start, $end]) {
            $data = $this->summary($start, $end);
            $this->assertSame('Perbandingan target bulanan hanya tersedia untuk satu bulan penuh.', $data['rows'][0]['status']);
            $this->assertStringContainsString(' sampai ', $data['rows'][0]['period_label']);
        }
    }

    public function test_missing_zero_or_invalid_targets_are_not_silently_replaced_with_another_year_or_200(): void
    {
        foreach ([[], ['2027' => 700], ['2026' => 0], ['2026' => -1], ['2026' => 'invalid'], ['2026' => true]] as $targets) {
            $this->targets($targets);
            $data = $this->summary();
            $this->assertNull($data['rows'][2]['target']);
            $this->assertSame('Target belum dikonfigurasi.', $data['rows'][2]['status']);
            $this->assertNull($data['sample_chart']['target']['yearly']);
            $this->assertSame([null], array_values(array_unique(array_column($data['sample_chart']['rows'], 'target'))));
        }
        foreach ([0, null, false, 'invalid'] as $target) {
            config(['statistics.targets.monthly_requests' => $target]);
            $this->assertSame('Target belum dikonfigurasi.', $this->summary()['rows'][0]['status']);
        }
    }

    public function test_non_sample_iku_source_is_not_presented_as_a_sample_target_achievement(): void
    {
        app(SettingsRepository::class)->put('iku.sources.C', 'requests_completed_count');
        $data = $this->summary();
        $this->assertNull($data['rows'][2]['target']);
        $this->assertStringContainsString('sumber indikator', $data['rows'][2]['status']);
        $this->assertNull($data['sample_chart']['target']['yearly']);
    }

    public function test_year_boundary_uses_the_target_and_completed_counts_for_the_displayed_year_only(): void
    {
        $this->targets(['2025' => 10, '2026' => 20]);
        $this->sample(['created_at' => '2025-11-01', 'testing_completed_at' => '2025-12-31 23:59:59']);
        $this->sample(['created_at' => '2025-11-01', 'testing_completed_at' => '2026-01-01 00:00:00']);
        $data = $this->summary('2026-01-01', '2026-01-31');
        $this->assertSame(20, $data['rows'][2]['target']);
        $this->assertSame(1, $data['rows'][2]['year_value']);
        $this->assertCount(1, $data['sample_chart']['rows']);
        $this->assertSame(1, $data['sample_chart']['rows'][0]['samples']);
        $this->assertSame(10, $this->summary('2025-12-01', '2025-12-31')['rows'][2]['target']);
    }

    public function test_end_of_period_and_legacy_completion_fallback_match_the_statistics(): void
    {
        $this->sample(['testing_completed_at' => null, 'updated_at' => '2026-10-31 23:59:59']);
        $this->sample(['testing_completed_at' => '2026-11-01 00:00:00']);
        $data = $this->summary();
        $this->assertSame(1, $data['rows'][2]['period_value']);
        $this->assertSame(1, $data['sample_chart']['total']);
        $this->assertSame(1, $data['period_counts']['total_samples_tested']);
    }

    public function test_revision_uses_the_latest_report_preserves_substance_correction_and_is_idempotent(): void
    {
        $this->sample();
        [$original, $substanceRevision] = $this->history();
        $beforeOriginal = $original->refresh()->getRawOriginal();
        $beforeRevision = $substanceRevision->refresh()->getRawOriginal();
        $this->mockPdf();

        $revision = $this->service()->reviseOperationalSummary($original, 'Perjelas ringkasan dan sumber target.');

        $this->assertSame(3, $revision->revision_number);
        $this->assertSame($original->id, $revision->revision_of_id);
        $this->assertSame($beforeOriginal, $original->fresh()->getRawOriginal());
        $this->assertSame($beforeRevision, $substanceRevision->fresh()->getRawOriginal());
        $this->assertSame('original-pdf', Storage::get($original->pdf_path));
        $this->assertSame('substance-pdf', Storage::get($substanceRevision->pdf_path));
        $this->assertNotSame($original->pdf_path, $revision->pdf_path);
        $this->assertNotSame($substanceRevision->pdf_path, $revision->pdf_path);
        $this->assertSame($substanceRevision->report_data['active_substances'], $revision->report_data['active_substances']);
        $this->assertSame($substanceRevision->report_data['statistics'], $revision->report_data['statistics']);
        $this->assertSame($substanceRevision->report_data['dashboard_appendix']['charts'][1], $revision->report_data['dashboard_appendix']['charts'][1]);
        $this->assertSame(2, $revision->report_data['dashboard_appendix']['summary_version']);
        $this->assertSame('Sampel selesai diuji', $revision->report_data['dashboard_appendix']['summary_table'][2]['category']);
        $this->assertSame($revision->id, $this->service()->reviseOperationalSummary($original, 'Retry aman.')->id);
        $this->assertSame(3, ConsolidatedReport::count());
        $this->assertSame(1, ActivityLog::where('action', 'CONSOLIDATED_REPORT_SUMMARY_REVISED')->count());
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_pdf_failure_rolls_back_new_revision_and_keeps_both_previous_files(): void
    {
        [$original, $source] = $this->history();
        Pdf::shouldReceive('loadView')->once()->andThrow(new RuntimeException('Simulated PDF failure'));
        try {
            $this->service()->reviseOperationalSummary($source, 'Perjelas ringkasan.');
            $this->fail('Expected PDF failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated PDF failure', $exception->getMessage());
        }
        $this->assertSame(2, ConsolidatedReport::count());
        $this->assertSame('original-pdf', Storage::get($original->pdf_path));
        $this->assertSame('substance-pdf', Storage::get($source->pdf_path));
        $this->assertCount(2, Storage::allFiles());
    }

    public function test_changed_source_counts_refuse_revision_instead_of_overwriting_the_old_snapshot(): void
    {
        [$original] = $this->history();
        TestRequest::factory()->createQuietly(['created_at' => '2026-10-10']);
        $this->expectException(ValidationException::class);
        $this->service()->reviseOperationalSummary($original, 'Perjelas ringkasan.');
    }

    public function test_preview_command_does_not_write_pdf_report_or_notification(): void
    {
        [$original] = $this->history();
        $this->artisan('reports:revise-summary', ['report' => $original->id])
            ->expectsOutput('Pratinjau saja. Tidak ada data, PDF, atau notifikasi yang ditulis.')->assertSuccessful();
        $this->assertSame(2, ConsolidatedReport::count());
        $this->assertCount(2, Storage::allFiles());
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
    }

    public function test_pdf_shows_clear_periods_and_never_draws_a_missing_target_as_zero(): void
    {
        $this->sample();
        [$report] = $this->history();
        $this->targets([]);
        $data = $report->report_data;
        $data['dashboard_appendix'] = $this->service()->getDashboardAppendixForPeriod(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
        $report->report_data = $data;
        $html = view('pdf.consolidated-report', ['report' => $report])->render();
        $this->assertStringContainsString('Sampel selesai diuji', $html);
        $this->assertStringContainsString('Oktober 2026: 1 sampel.', $html);
        $this->assertStringContainsString('1 Januari sampai 31 Oktober 2026: 1 sampel.', $html);
        $this->assertStringContainsString('Target tahunan belum dikonfigurasi.', $html);
        $this->assertStringNotContainsString('Mendekati Target', $html);
        $this->assertStringNotContainsString('periode ini,', $html);
        preg_match_all('/data:image\/svg\+xml;base64,([A-Za-z0-9+\/=]+)/', $html, $matches);
        $this->assertCount(2, $matches[1]);
        $sampleSvg = base64_decode($matches[1][1]);
        $this->assertSame(1, substr_count($sampleSvg, '<polyline'));
        $this->assertStringNotContainsString('Target tahunan', $sampleSvg);
    }

    private function targets(array $targets): void
    {
        app(SettingsRepository::class)->putMany(['iku.target_samples_by_year' => $targets, 'iku.sources.C' => 'samples_completed_count', 'iku.period_mode' => 'quarterly']);
    }

    private function sample(array $attributes = []): Sample
    {
        return Sample::factory()->createQuietly(array_replace(['created_at' => '2026-09-01', 'updated_at' => '2026-10-15',
            'sample_status' => 'tested', 'testing_completed_at' => '2026-10-15', 'active_substance' => 'Tramadol'], $attributes));
    }

    private function service(): ConsolidatedReportService
    {
        return app(ConsolidatedReportService::class);
    }

    private function summary(string $start = '2026-10-01', string $end = '2026-10-31'): array
    {
        return $this->service()->getOperationalSummaryForPeriod(Carbon::parse($start), Carbon::parse($end));
    }

    private function history(): array
    {
        $preview = $this->service()->getPreviewData('monthly', Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        unset($preview['dashboard_appendix']['summary_version']);
        $preview['dashboard_appendix']['summary_table'] = [['category' => 'Jumlah Sampel', 'period_value' => 0, 'year_value' => 0, 'target' => '200/tahun (IKU)', 'status' => 'Mendekati Target']];
        $preview['dashboard_appendix']['charts'][5] = ['title' => 'Sampel vs Target IKU', 'type' => 'mixed', 'rows' => [], 'total' => 0];
        $original = ConsolidatedReport::create(['period_type' => 'monthly', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'period_label' => 'Bulan Oktober 2026', 'report_data' => $preview, 'comparison_data' => [],
            'narrative_sections' => ['opening' => '', 'closing' => ''],
            'signers' => ConsolidatedReportService::DEFAULT_SIGNERS_STRUCTURE, 'generated_at' => now(),
            'pdf_path' => 'reports/consolidated/original.pdf']);
        Storage::put($original->pdf_path, 'original-pdf');
        $source = $original->replicate();
        $source->revision_of_id = $original->id;
        $source->revision_number = 2;
        $source->revision_reason = 'Koreksi zat aktif';
        $source->pdf_path = 'reports/consolidated/substances.pdf';
        $source->save();
        Storage::put($source->pdf_path, 'substance-pdf');

        return [$original, $source];
    }

    private function mockPdf(): void
    {
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('output')->andReturn('summary-pdf');
        Pdf::shouldReceive('loadView')->once()->andReturn($pdf);
    }
}
