<?php

declare(strict_types=1);

use App\Models\ConsolidatedReport;
use App\Models\User;
use App\Services\ConsolidatedReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function revisionSigners(): array
{
    return [
        ['role' => 'Pembuat', 'name' => 'Pembuat', 'position' => 'Jabatan', 'nip' => null],
        ['role' => 'Pemeriksa', 'name' => 'Pemeriksa', 'position' => 'Jabatan', 'nip' => null],
        ['role' => 'Pengesah', 'name' => 'Pengesah', 'position' => 'Jabatan', 'nip' => null],
    ];
}

function stubRevisionPdf(): void
{
    $pdf = Mockery::mock(DomPdf::class);
    $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
    $pdf->shouldReceive('output')->once()->andReturn('%PDF-test');

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdf.consolidated-report', Mockery::type('array'))
        ->andReturn($pdf);
}

test('revises a report without replacing its original snapshot', function () {
    Storage::fake('local');
    settings_fake(['iku.target_samples_by_year' => ['2026' => 200]], true);

    $base = ConsolidatedReport::query()->create([
        'period_type' => 'monthly',
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'period_label' => 'Bulan Januari 2026',
        'report_data' => ['sentinel' => 'original snapshot'],
        'comparison_data' => ['changes' => []],
        'narrative_sections' => ['opening' => 'Pembuka', 'closing' => 'Penutup'],
        'signers' => revisionSigners(),
        'generated_at' => now(),
        'is_auto_generated' => false,
    ]);

    stubRevisionPdf();

    $revision = app(ConsolidatedReportService::class)->revise(
        $base,
        'Koreksi data sumber sampel yang sudah diverifikasi.',
        User::factory()->create()->id
    );

    expect($revision->revision_of_id)->toBe($base->id)
        ->and($revision->revision_number)->toBe(2)
        ->and($revision->revision_reason)->toBe('Koreksi data sumber sampel yang sudah diverifikasi.')
        ->and($revision->report_data)->toHaveKey('metadata')
        ->and($revision->pdf_path)->toContain("reports/consolidated/{$revision->id}/")
        ->and($base->fresh()->report_data)->toBe(['sentinel' => 'original snapshot'])
        ->and($base->revisions()->count())->toBe(1);
});

test('revision endpoint requires a meaningful reason and preserves authorization', function () {
    $report = ConsolidatedReport::query()->create([
        'period_type' => 'monthly',
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'period_label' => 'Bulan Januari 2026',
        'report_data' => [],
        'comparison_data' => ['changes' => []],
        'narrative_sections' => ['opening' => '', 'closing' => ''],
        'signers' => revisionSigners(),
        'generated_at' => now(),
        'is_auto_generated' => false,
    ]);
    $user = User::factory()->create();
    Gate::shouldReceive('authorize')->with('statistik.export', [])->andReturn(true);

    $this->actingAs($user)
        ->postJson(route('consolidated-reports.revisions.store', $report), ['reason' => 'Tidak'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

test('a published report in a revision chain cannot be deleted', function () {
    $base = ConsolidatedReport::query()->create([
        'period_type' => 'quarterly',
        'period_start' => '2026-07-01',
        'period_end' => '2026-09-30',
        'period_label' => 'Triwulan 3 Tahun 2026',
        'report_data' => [],
        'comparison_data' => ['changes' => []],
        'narrative_sections' => ['opening' => '', 'closing' => ''],
        'signers' => revisionSigners(),
        'generated_at' => now(),
        'is_auto_generated' => false,
    ]);

    ConsolidatedReport::query()->create([
        'period_type' => $base->period_type,
        'period_start' => $base->period_start,
        'period_end' => $base->period_end,
        'period_label' => $base->period_label,
        'report_data' => [],
        'comparison_data' => ['changes' => []],
        'narrative_sections' => ['opening' => '', 'closing' => ''],
        'signers' => revisionSigners(),
        'generated_at' => now(),
        'is_auto_generated' => false,
        'revision_of_id' => $base->id,
        'revision_number' => 2,
        'revision_reason' => 'Koreksi yang tercatat untuk laporan sebelumnya.',
    ]);

    Gate::shouldReceive('authorize')->with('statistik.export', [])->andReturn(true);

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('consolidated-reports.destroy', $base))
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    expect($base->fresh())->not->toBeNull();
});
