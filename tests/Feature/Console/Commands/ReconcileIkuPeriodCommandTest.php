<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('IKU reconciliation command reports only aggregate data and identifies itself as read-only', function () {
    $this->artisan('iku:reconcile', [
        '--start' => '2026-07-01',
        '--end' => '2026-09-30',
    ])
        ->expectsOutputToContain('"read_only": true')
        ->assertSuccessful();

    expect(App\Models\TestRequest::query()->count())->toBe(0)
        ->and(App\Models\ConsolidatedReport::query()->count())->toBe(0);
});

test('IKU reconciliation rejects report periods that do not match the stored report', function () {
    $report = App\Models\ConsolidatedReport::query()->create([
        'period_type' => 'quarterly',
        'period_start' => '2026-07-01',
        'period_end' => '2026-09-30',
        'period_label' => 'Triwulan 3 Tahun 2026',
        'report_data' => ['iku' => ['raw_counts' => []]],
        'comparison_data' => ['changes' => []],
        'narrative_sections' => ['opening' => '', 'closing' => ''],
        'signers' => [],
        'generated_at' => now(),
        'is_auto_generated' => false,
    ]);

    $this->artisan('iku:reconcile', [
        '--start' => '2026-04-01',
        '--end' => '2026-06-30',
        '--report-id' => $report->id,
    ])->assertExitCode(2);

    expect($report->fresh()->report_data)->toHaveKey('iku');
});
