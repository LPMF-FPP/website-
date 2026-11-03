<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\ActivityLog;
use App\Models\ConsolidatedReport;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestResult;
use App\Models\User;
use App\Services\ConsolidatedReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ConsolidatedReportSubstancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_uses_the_tested_sample_cohort_not_the_intake_month(): void
    {
        $sample = $this->sample();
        $this->interpretation($sample, ['test_result' => 'positive', 'detected_substance' => 'Tramadol']);
        $this->sample(['created_at' => '2026-10-05', 'testing_completed_at' => null, 'updated_at' => '2026-10-05', 'sample_status' => 'received', 'active_substance' => 'Paracetamol']);

        $data = $this->breakdown();
        $this->assertSame(1, $data['sample_total']);
        $this->assertSame(['Tramadol'], array_column($data['items'], 'name'));
        $this->assertSame($this->service()->getStatisticsForPeriod($this->start(), $this->end())['total_samples_tested'], $data['sample_total']);
        $this->assertTrue($data['is_complete']);
    }

    public function test_updated_interpretation_wins_over_old_positive_data(): void
    {
        $sample = $this->sample(['active_substance' => 'Tramadol']);
        $this->interpretation($sample, ['test_result' => 'positive', 'detected_substance' => 'Tramadol'], '2026-10-02');
        $this->interpretation($sample, ['test_result' => 'negative', 'detected_substance' => 'Stale value'], '2026-10-03');

        $data = $this->breakdown();
        $this->assertSame([], $data['items']);
        $this->assertSame(1, $data['negative_samples']);
        $this->assertSame(0, $data['missing_result_samples']);
        $this->assertStringContainsString('negatif', $data['message']);
    }

    public function test_secondary_substances_are_counted_once_per_sample_and_negatives_are_not_counted(): void
    {
        $sample = $this->sample();
        $this->interpretation($sample, [
            'test_result' => 'positive', 'detected_substance' => 'Tramadol; tramadol',
            'multi_interpretations' => [
                ['test_result' => 'positive', 'detected_substance' => 'Tramadol, Trihexyphenidyl'],
                ['test_result' => 'negative', 'detected_substance' => 'Paracetamol'],
            ],
        ]);

        $data = $this->breakdown();
        $this->assertSame(1, $data['positive_samples']);
        $this->assertSame(2, $data['total']);
        $this->assertSame([1, 1], array_column($data['items'], 'count'));
        $this->assertSame([50.0, 50.0], array_column($data['items'], 'percentage'));
    }

    public function test_fallback_is_per_sample_and_does_not_skip_legacy_results_when_other_samples_have_values(): void
    {
        $this->sample(['active_substance' => 'Trihexyphenidyl']);
        $sample = $this->sample();
        $this->legacyResult($sample, 'positive');

        $data = $this->breakdown();
        $this->assertSame(2, $data['positive_samples']);
        $this->assertSame(2, $data['legacy_samples']);
        $this->assertEqualsCanonicalizing(['Trihexyphenidyl', 'Tramadol'], array_column($data['items'], 'name'));
    }

    public function test_legacy_negative_does_not_use_stale_sample_substance(): void
    {
        $sample = $this->sample(['active_substance' => 'Tramadol']);
        $this->legacyResult($sample, 'negative');

        $this->assertSame(1, $this->breakdown()['negative_samples']);
        $this->assertSame(0, $this->breakdown()['total']);
    }

    public function test_missing_results_are_not_reported_as_negative_and_prevent_publication(): void
    {
        $this->sample();
        $data = $this->breakdown();
        $this->assertSame(1, $data['missing_result_samples']);
        $this->assertFalse($data['is_complete']);
        $this->assertSame(0, $data['negative_samples']);
        $this->assertStringContainsString('belum lengkap', $data['message']);

        try {
            $this->service()->generate($this->input());
            $this->fail('Incomplete reports must not be published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('active_substances', $exception->errors());
        }
        $this->assertSame(0, ConsolidatedReport::count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_draft_and_future_interpretations_cannot_be_used_as_a_completed_result(): void
    {
        $sample = $this->sample(['active_substance' => 'Tramadol']);
        $this->interpretation($sample, ['test_result' => 'positive', 'detected_substance' => 'Tramadol'], null);
        $sample = $this->sample(['active_substance' => 'Tramadol']);
        $this->interpretation($sample, ['test_result' => 'positive', 'detected_substance' => 'Tramadol'], '2026-11-02');

        $this->assertSame(2, $this->breakdown()['missing_result_samples']);
        $this->assertSame([], $this->breakdown()['items']);
    }

    public function test_no_tested_samples_is_distinct_from_negative_and_legacy_placeholders_are_not_chemicals(): void
    {
        $this->assertSame('Tidak ada sampel teruji pada periode ini.', $this->breakdown()['message']);
        foreach (['Tidak terdeteksi', '123', 'null', '-'] as $value) {
            $this->sample(['active_substance' => $value]);
        }
        $data = $this->breakdown();
        $this->assertSame(4, $data['missing_result_samples']);
        $this->assertSame([], $data['items']);
    }

    public function test_year_boundary_includes_the_last_second_and_excludes_the_next_period(): void
    {
        $sample = $this->sample(['testing_completed_at' => '2026-12-31 23:59:59', 'updated_at' => '2026-12-31 23:59:59']);
        $this->interpretation($sample, ['test_result' => 'positive', 'detected_substance' => 'Tramadol'], '2026-12-31 23:59:59');
        $this->sample(['testing_completed_at' => '2027-01-01 00:00:00', 'active_substance' => 'Paracetamol']);
        $data = $this->service()->getActiveSubstancesForPeriod(Carbon::parse('2026-12-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(1, $data['sample_total']);
        $this->assertSame(['Tramadol'], array_column($data['items'], 'name'));
    }

    public function test_legacy_completion_date_fallback_is_shared_with_statistics(): void
    {
        $this->sample(['testing_completed_at' => null, 'updated_at' => '2026-10-31 23:59:59', 'active_substance' => 'Tramadol']);
        $this->sample(['testing_completed_at' => null, 'updated_at' => '2026-11-01', 'active_substance' => 'Paracetamol']);
        $data = $this->breakdown();
        $this->assertSame(1, $data['sample_total']);
        $this->assertSame(1, $this->service()->getStatisticsForPeriod($this->start(), $this->end())['total_samples_tested']);
    }

    public function test_http_publication_returns_actionable_validation_error_and_keeps_authorization(): void
    {
        $this->sample();
        $user = User::factory()->create(['role' => 'admin']);
        Gate::define('statistik.export', fn (User $actor) => $actor->id === $user->id);
        $this->actingAs($user)->postJson(route('consolidated-reports.store'), $this->input())
            ->assertUnprocessable()->assertJsonValidationErrors('active_substances');
        $this->assertSame(0, ConsolidatedReport::count());
        $this->actingAs(User::factory()->create(['role' => 'investigator']))
            ->postJson(route('consolidated-reports.store'), $this->input())->assertForbidden();
    }

    public function test_revision_preserves_original_pdf_and_snapshot_and_is_idempotent_without_notifications(): void
    {
        $sample = $this->sample();
        $this->interpretation($sample, ['test_result' => 'positive', 'detected_substance' => 'Tramadol']);
        $original = $this->original();
        $before = $original->refresh()->getRawOriginal();
        $this->mockPdf();

        $revision = $this->service()->reviseActiveSubstances($original, 'Koreksi sumber rekap zat aktif.');

        $this->assertSame($before, $original->fresh()->getRawOriginal());
        $this->assertSame('original-pdf', Storage::get($original->pdf_path));
        $this->assertNotSame($original->pdf_path, $revision->pdf_path);
        $this->assertSame('revision-pdf', Storage::get($revision->pdf_path));
        $this->assertSame($original->id, $revision->revision_of_id);
        $this->assertSame(2, $revision->revision_number);
        $this->assertSame(['Tramadol'], array_column($revision->report_data['active_substances']['items'], 'name'));
        $this->assertSame($original->report_data['statistics'], $revision->report_data['statistics']);
        $this->assertSame(1, $revision->report_data['dashboard_appendix']['summary_cards'][2]['value']);
        $this->assertSame('Tramadol', $revision->report_data['dashboard_appendix']['charts'][1]['rows'][0]['label']);
        $this->assertSame($original->report_data['dashboard_appendix']['charts'][4], $revision->report_data['dashboard_appendix']['charts'][4]);
        $this->assertSame($revision->id, $this->service()->reviseActiveSubstances($original, 'Retry aman.')->id);
        $this->assertSame(2, ConsolidatedReport::count());
        $this->assertSame(1, ActivityLog::where('action', 'CONSOLIDATED_REPORT_SUBSTANCES_REVISED')->count());
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_failed_revision_pdf_rolls_back_record_and_preserves_original(): void
    {
        $this->sample(['active_substance' => 'Tramadol']);
        $original = $this->original();
        Pdf::shouldReceive('loadView')->once()->andThrow(new RuntimeException('Simulated PDF failure'));
        try {
            $this->service()->reviseActiveSubstances($original, 'Koreksi rekap.');
            $this->fail('Expected PDF failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated PDF failure', $exception->getMessage());
        }
        $this->assertSame(1, ConsolidatedReport::count());
        $this->assertSame('original-pdf', Storage::get($original->pdf_path));
        $this->assertSame([$original->pdf_path], Storage::allFiles());
        $this->assertSame(0, ActivityLog::where('action', 'CONSOLIDATED_REPORT_SUBSTANCES_REVISED')->count());
    }

    public function test_revision_refuses_changed_cohort_instead_of_rewriting_other_metrics(): void
    {
        $this->sample(['active_substance' => 'Tramadol']);
        $original = $this->original();
        $this->sample(['active_substance' => 'Trihexyphenidyl']);
        $this->expectException(ValidationException::class);
        $this->service()->reviseActiveSubstances($original, 'Koreksi rekap.');
    }

    public function test_revision_command_defaults_to_preview_and_does_not_write_or_send(): void
    {
        $this->sample(['active_substance' => 'Tramadol']);
        $original = $this->original();
        $this->artisan('reports:revise-active-substances', ['report' => $original->id])
            ->expectsOutput('Pratinjau saja. Tidak ada data, PDF, atau notifikasi yang ditulis.')->assertSuccessful();
        $this->assertSame(1, ConsolidatedReport::count());
        $this->assertSame([$original->pdf_path], Storage::allFiles());
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
    }

    public function test_inconclusive_and_incomplete_secondary_results_block_publication(): void
    {
        $sample = $this->sample();
        $this->legacyResult($sample, 'inconclusive');
        $sample = $this->sample();
        $this->interpretation($sample, ['test_result' => 'positive', 'detected_substance' => 'Tramadol',
            'multi_interpretations' => [['test_result' => 'positive']]]);

        $data = $this->breakdown();
        $this->assertFalse($data['is_complete']);
        $this->assertSame(2, $data['missing_result_samples']);
        $this->assertSame(0, $data['negative_samples']);
    }

    public function test_original_generation_rolls_back_if_pdf_fails(): void
    {
        $this->sample(['active_substance' => 'Tramadol']);
        Pdf::shouldReceive('loadView')->once()->andThrow(new RuntimeException('Simulated PDF failure'));
        try {
            $this->service()->generate($this->input());
            $this->fail('Expected PDF failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated PDF failure', $exception->getMessage());
        }
        $this->assertSame(0, ConsolidatedReport::count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_pdf_distinguishes_incomplete_empty_and_negative_results(): void
    {
        $original = $this->original();
        $data = $original->report_data;
        foreach (['Data hasil belum lengkap.', 'Tidak ada sampel teruji pada periode ini.', 'Seluruh hasil pengujian yang terbaca pada periode ini negatif.'] as $message) {
            $data['active_substances'] = ['items' => [], 'total' => 0, 'message' => $message];
            $original->report_data = $data;
            $html = view('pdf.consolidated-report', ['report' => $original])->render();
            $this->assertStringContainsString($message, $html);
            $this->assertStringNotContainsString('Tidak ada zat aktif terdeteksi pada periode ini.', $html);
        }
    }

    private function sample(array $attributes = []): Sample
    {
        return Sample::factory()->createQuietly(array_replace([
            'created_at' => '2026-09-10', 'updated_at' => '2026-10-10',
            'sample_status' => 'tested', 'testing_completed_at' => '2026-10-10', 'active_substance' => null,
        ], $attributes));
    }

    private function interpretation(Sample $sample, array $metadata, ?string $completed = '2026-10-10'): void
    {
        SampleTestProcess::updateOrCreate(['sample_id' => $sample->id, 'stage' => 'interpretation'], ['metadata' => $metadata, 'completed_at' => $completed]);
    }

    private function legacyResult(Sample $sample, string $status): void
    {
        $result = new TestResult;
        $result->forceFill(['sample_id' => $sample->id, 'tested_by' => User::factory()->create()->id,
            'test_method' => 'UV-VIS', 'equipment_used' => 'Instrumen Uji', 'test_conclusion' => 'Hasil uji',
            'active_substances' => ['Tramadol'], 'result_status' => $status, 'created_at' => '2026-10-02']);
        $result->save();
    }

    private function service(): ConsolidatedReportService
    {
        return app(ConsolidatedReportService::class);
    }

    private function start(): Carbon
    {
        return Carbon::parse('2026-10-01');
    }

    private function end(): Carbon
    {
        return Carbon::parse('2026-10-31')->endOfDay();
    }

    private function breakdown(): array
    {
        return $this->service()->getActiveSubstancesForPeriod($this->start(), $this->end());
    }

    private function input(): array
    {
        return ['period_type' => 'monthly', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'narratives' => ['opening' => 'Pembuka', 'closing' => 'Penutup'],
            'signers' => array_map(fn ($role) => ['role' => $role, 'name' => 'Petugas Uji', 'position' => 'Pengujian', 'nip' => '123'], ['Pembuat', 'Pemeriksa', 'Pengesah'])];
    }

    private function original(): ConsolidatedReport
    {
        $preview = $this->service()->getPreviewData('monthly', $this->start(), $this->end());
        $preview['active_substances'] = ['items' => [], 'total' => 0];
        $preview['dashboard_appendix']['summary_cards'][2]['value'] = 0;
        $preview['dashboard_appendix']['charts'][1]['rows'] = [];
        $preview['dashboard_appendix']['charts'][1]['total'] = 0;
        $original = ConsolidatedReport::create($this->input() + [
            'period_label' => 'Bulan Oktober 2026', 'report_data' => $preview,
            'comparison_data' => $preview['comparison'], 'narrative_sections' => ['opening' => '', 'closing' => ''],
            'generated_at' => now(), 'pdf_path' => 'reports/consolidated/original.pdf',
        ]);
        Storage::put($original->pdf_path, 'original-pdf');

        return $original;
    }

    private function mockPdf(): void
    {
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('output')->andReturn('revision-pdf');
        Pdf::shouldReceive('loadView')->once()->andReturn($pdf);
    }
}
