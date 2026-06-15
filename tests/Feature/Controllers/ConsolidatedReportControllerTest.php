<?php

namespace Tests\Feature\Controllers;

use App\Models\ConsolidatedReport;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\ConsolidatedReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsolidatedReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_passes_default_signers_to_view()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        // Mock the authorize call on controller to bypass authorization
        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        // Mock Service
        $this->mock(ConsolidatedReportService::class, function ($mock) {
            $mock->shouldReceive('getDefaultSigners')
                ->once()
                ->andReturn([['role' => 'Mocked']]);
        });

        $response = $this->get(route('consolidated-reports.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertViewIs('statistics.partials.consolidated-form');
        $response->assertViewHas('defaultSigners', [['role' => 'Mocked']]);
        $response->assertSee('x-if="step === \'preview\' && previewData"', false);
    }

    public function test_save_default_signers_logs_changes()
    {
        /** @var User $user */
        $user = User::factory()->create();

        Log::spy();

        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        $oldSigners = [['role' => 'old_role', 'name' => 'Old Name']];
        SystemSetting::create(['key' => 'consolidated_report.default_signers', 'value' => $oldSigners]);

        $newSigners = [['role' => 'new_role', 'name' => 'New Name']];

        $response = $this->actingAs($user)
            ->putJson(route('consolidated-reports.save-default-signers'), ['signers' => $newSigners]);

        $response->assertOk();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function ($message, $context) use ($user, $oldSigners, $newSigners) {
                return str_contains($message, 'Default signers updated by user '.$user->id)
                    && ($context['old'] ?? null) == $oldSigners
                    && ($context['new'] ?? null) == $newSigners;
            });
    }

    public function test_store_calls_send_generation_notification()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization
        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        // Needed for FormRequest authorization check
        \Illuminate\Support\Facades\Gate::shouldReceive('forUser')->andReturnSelf();
        \Illuminate\Support\Facades\Gate::shouldReceive('check')->andReturn(true);
        \Illuminate\Support\Facades\Gate::shouldReceive('any')->andReturn(true);

        $report = new \App\Models\ConsolidatedReport;
        $report->id = 1;
        $report->period_label = 'Test Period';
        $report->download_url = 'http://example.com/report.pdf';

        $this->mock(ConsolidatedReportService::class, function ($mock) use ($report) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn($report);

            $mock->shouldReceive('sendGenerationNotification')
                ->once()
                ->with($report, true);
        });

        $data = [
            'period_type' => 'monthly',
            'period_start' => '2023-01-01',
            'period_end' => '2023-01-31',
            'signers' => [
                [
                    'role' => 'Pembuat',
                    'name' => 'Signer 1',
                    'position' => 'Position 1',
                    'nip' => '123',
                ],
                [
                    'role' => 'Pemeriksa',
                    'name' => 'Signer 2',
                    'position' => 'Position 2',
                    'nip' => '456',
                ],
                [
                    'role' => 'Pengesah',
                    'name' => 'Signer 3',
                    'position' => 'Position 3',
                    'nip' => '789',
                ],
            ],
        ];

        $response = $this->postJson(route('consolidated-reports.store'), $data);

        $response->assertStatus(201);
    }

    public function test_preview_includes_dashboard_appendix_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        $this->seedDashboardAppendixData();
        $this->seedDashboardAppendixDataAfterPeriod();

        $response = $this->postJson(route('consolidated-reports.preview'), [
            'period_type' => 'biweekly',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dashboard_appendix.summary_cards.0.label', 'Permintaan Periode Ini')
            ->assertJsonPath('data.dashboard_appendix.summary_table.0.category', 'Permintaan Pengujian')
            ->assertJsonCount(6, 'data.dashboard_appendix.charts')
            ->assertJsonPath('data.dashboard_appendix.charts.4.rows.11.requests', 1)
            ->assertJsonPath('data.dashboard_appendix.charts.5.rows.11.samples', 1);
    }

    public function test_preview_counts_records_until_end_of_period_day(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        $investigator = Investigator::factory()->create();
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'created_at' => '2026-01-15 23:30:00',
            'submitted_at' => '2026-01-15 23:30:00',
            'completed_at' => '2026-01-15 23:45:00',
            'ready_for_delivery_at' => '2026-01-15 23:40:00',
            'status' => 'completed',
        ]);
        Sample::factory()->create([
            'test_request_id' => $request->id,
            'created_at' => '2026-01-15 23:35:00',
            'testing_completed_at' => '2026-01-15 23:50:00',
            'sample_status' => 'tested',
            'active_substance' => 'Tramadol; Metamfetamin',
        ]);

        $response = $this->postJson(route('consolidated-reports.preview'), [
            'period_type' => 'biweekly',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-15',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.statistics.total_requests_received', 1)
            ->assertJsonPath('data.statistics.total_samples_received', 1)
            ->assertJsonPath('data.dashboard_appendix.charts.1.rows.0.label', 'Tramadol')
            ->assertJsonPath('data.dashboard_appendix.charts.1.rows.1.label', 'Metamfetamin');
    }

    public function test_store_persists_dashboard_appendix_and_pdf_view_receives_it(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);
        \Illuminate\Support\Facades\Gate::shouldReceive('forUser')->andReturnSelf();
        \Illuminate\Support\Facades\Gate::shouldReceive('check')->andReturn(true);

        settings_fake([
            'consolidated_report.notify_on_generate' => false,
            'consolidated_report.default_narratives.opening' => 'Pembuka {period_label}',
            'consolidated_report.default_narratives.closing' => 'Penutup {period_label}',
        ], true);

        $this->seedDashboardAppendixData();

        $capturedReport = null;
        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('pdf-content');

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$capturedReport) {
                $this->assertSame('pdf.consolidated-report', $view);
                $capturedReport = $data['report'] ?? null;

                return true;
            })
            ->andReturn($mockPdf);

        $response = $this->postJson(route('consolidated-reports.store'), [
            'period_type' => 'monthly',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'narratives' => ['opening' => 'Pembuka', 'closing' => 'Penutup'],
            'signers' => $this->validSigners(),
        ]);

        $response->assertCreated();

        $report = ConsolidatedReport::query()->firstOrFail();
        $html = view('pdf.consolidated-report', ['report' => $report])->render();

        $this->assertArrayHasKey('dashboard_appendix', $report->report_data);
        $this->assertStringContainsString('Lampiran Statistik Dashboard', $html);
        $this->assertNotNull($capturedReport);
        $this->assertArrayHasKey('dashboard_appendix', $capturedReport->report_data);
    }

    public function test_pdf_appendix_renders_valid_visual_contract(): void
    {
        $report = ConsolidatedReport::create([
            'period_type' => 'monthly',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'period_label' => 'Bulan Januari 2026',
            'report_data' => [
                'statistics' => [],
                'active_substances' => ['items' => [], 'total' => 0],
                'processing_time' => ['avg_days' => 0, 'total' => 0, 'categories' => []],
                'satisfaction' => ['avg_score' => 0, 'total_respondents' => 0, 'ratings' => []],
                'gender' => ['total' => 0, 'items' => []],
                'age_range' => ['total' => 0, 'items' => []],
                'jurisdiction' => ['items' => []],
                'dashboard_appendix' => [
                    'summary_cards' => [null, ['label' => 'Permintaan Periode Ini', 'value' => 1, 'note' => 'Dummy']],
                    'summary_table' => [['category' => 'Permintaan Pengujian', 'period_value' => 1, 'year_value' => 1, 'target' => '32/bulan', 'status' => 'Normal']],
                    'charts' => [
                        ['title' => 'Asal User', 'type' => 'pie', 'rows' => [
                            ['label' => 'A', 'count' => 5, 'percentage' => 50],
                            ['label' => 'B', 'count' => 2, 'percentage' => 20],
                            ['label' => 'C', 'count' => 1, 'percentage' => 10],
                            ['label' => 'D', 'count' => 1, 'percentage' => 10],
                            ['label' => 'E', 'count' => 1, 'percentage' => 10],
                            ['label' => 'F', 'count' => 1, 'percentage' => 10],
                        ], 'total' => 11],
                        ['title' => 'Permintaan per Bulan', 'type' => 'line', 'rows' => [
                            ['label' => 'Jan 2026', 'requests' => 4, 'completed' => 2],
                            ['label' => 'Feb 2026', 'requests' => 8, 'completed' => 4],
                        ], 'total' => 12],
                        ['title' => 'Sampel vs Target IKU', 'type' => 'mixed', 'rows' => [
                            ['label' => 'Jan 2026', 'samples' => 8, 'target' => 16.7],
                            ['label' => 'Feb 2026', 'samples' => 24, 'target' => 16.7],
                        ], 'total' => 32, 'target' => ['yearly' => 200, 'monthly_average' => 16.7]],
                        null,
                    ],
                ],
            ],
            'comparison_data' => ['changes' => []],
            'narrative_sections' => ['opening' => '', 'closing' => ''],
            'signers' => $this->validSigners(),
            'generated_at' => now(),
            'is_auto_generated' => false,
        ]);

        $html = view('pdf.consolidated-report', ['report' => $report])->render();

        $this->assertStringContainsString("</table>\n    </div>\n\n    <!-- II. Rekap Zat Aktif -->", $html);
        $this->assertStringContainsString('Status: <strong>Normal</strong>', $html);
        $this->assertStringContainsString('Lainnya', $html);
        $this->assertStringContainsString('class="trend-table"', $html);
        $this->assertStringContainsString('<strong>16.7</strong>', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('<polyline', $html);
        $this->assertStringNotContainsString('<circle', $html);
        $this->assertNotEmpty(Pdf::loadView('pdf.consolidated-report', ['report' => $report])->setPaper('a4', 'portrait')->output());
    }

    public function test_legacy_pdf_without_dashboard_appendix_still_renders(): void
    {
        $report = ConsolidatedReport::create([
            'period_type' => 'monthly',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'period_label' => 'Bulan Januari 2026',
            'report_data' => [
                'statistics' => [],
                'active_substances' => ['items' => [], 'total' => 0],
                'processing_time' => ['avg_days' => 0, 'total' => 0, 'categories' => []],
                'satisfaction' => ['avg_score' => 0, 'total_respondents' => 0, 'ratings' => []],
                'gender' => ['total' => 0, 'items' => []],
                'age_range' => ['total' => 0, 'items' => []],
                'jurisdiction' => ['items' => []],
            ],
            'comparison_data' => ['changes' => []],
            'narrative_sections' => ['opening' => '', 'closing' => ''],
            'signers' => $this->validSigners(),
            'generated_at' => now(),
            'is_auto_generated' => false,
        ]);

        $html = view('pdf.consolidated-report', ['report' => $report])->render();

        $this->assertStringContainsString('Laporan Gabungan Periodik', $html);
    }

    private function seedDashboardAppendixData(): void
    {
        $investigator = Investigator::factory()->create(['jurisdiction' => 'Polda Metro Jaya']);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'created_at' => '2026-01-10 08:00:00',
            'submitted_at' => '2026-01-10 08:00:00',
            'ready_for_delivery_at' => '2026-01-14 08:00:00',
            'completed_at' => '2026-01-15 08:00:00',
            'status' => 'completed',
            'suspect_gender' => 'Laki-laki',
            'suspect_age' => 24,
        ]);

        Sample::factory()->create([
            'test_request_id' => $request->id,
            'created_at' => '2026-01-10 08:30:00',
            'updated_at' => '2026-01-14 08:30:00',
            'testing_completed_at' => '2026-01-14 08:30:00',
            'sample_status' => 'tested',
            'active_substance' => 'Tramadol',
        ]);
    }

    private function seedDashboardAppendixDataAfterPeriod(): void
    {
        $investigator = Investigator::factory()->create(['jurisdiction' => 'Polda Metro Jaya']);
        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'created_at' => '2026-01-20 08:00:00',
            'submitted_at' => '2026-01-20 08:00:00',
            'completed_at' => '2026-01-22 08:00:00',
            'status' => 'completed',
            'suspect_gender' => 'Perempuan',
            'suspect_age' => 31,
        ]);

        Sample::factory()->create([
            'test_request_id' => $request->id,
            'created_at' => '2026-01-20 08:30:00',
            'sample_status' => 'tested',
            'active_substance' => 'Paracetamol',
        ]);
    }

    private function validSigners(): array
    {
        return [
            ['role' => 'Pembuat', 'name' => 'Signer 1', 'position' => 'Position 1', 'nip' => '123'],
            ['role' => 'Pemeriksa', 'name' => 'Signer 2', 'position' => 'Position 2', 'nip' => '456'],
            ['role' => 'Pengesah', 'name' => 'Signer 3', 'position' => 'Position 3', 'nip' => '789'],
        ];
    }
}
