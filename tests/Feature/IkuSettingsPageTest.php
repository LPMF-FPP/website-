<?php

declare(strict_types=1);

use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\IkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create base settings
    settings_forget_cache();
});

// =========================================
// Settings Page IKU Section Rendering Tests
// =========================================

test('settings page includes iku section navigation', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk()
        ->assertSee('Perhitungan IKU', false);
});

test('settings page renders iku partial blade template', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk()
        // IKU section identifiers from iku.blade.php
        ->assertSee('Bobot Komponen IKU', false)
        ->assertSee('Mode Periode', false)
        ->assertSee('Target Sampel per Tahun', false);
});

test('settings page shows iku weight inputs', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk()
        ->assertSee('Registrasi Permohonan', false)
        ->assertSee('Pemeriksaan Lab', false)
        ->assertSee('Laporan Hasil', false)
        ->assertSee('Survei Kepuasan', false);
});

test('settings page includes iku alpine bindings', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk()
        ->assertSee('x-model.number="client.state.form.iku', false);
});

test('settings page includes iku preview section', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk()
        ->assertSee('ikuPreview', false);
});

test('settings page includes survey export section', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk()
        ->assertSee('Export Rekap Survey', false);
});

test('settings page non-admin cannot access', function () {
    // Use valid role from database enum
    $user = User::factory()->create(['role' => 'investigator']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertForbidden();
});

test('settings page requires authentication', function () {
    $response = $this->get(route('settings.index'));

    $response->assertRedirect(route('login'));
});

// =========================================
// IKU API Integration Tests (from Settings Page)
// =========================================

test('settings page initial data includes iku config via api', function () {
    // Configure IKU settings
    SystemSetting::updateOrCreate(
        ['key' => 'iku'],
        ['value' => [
            'weights' => [
                'registration' => 10,
                'lab_exam' => 40,
                'report' => 40,
                'survey' => 10,
            ],
            'period_mode' => 'monthly',
            'target_samples_by_year' => [
                '2024' => 100,
            ],
        ]]
    );
    settings_forget_cache();

    $user = User::factory()->create(['role' => 'admin']);

    // API endpoint returns IKU config
    $response = $this->actingAs($user)
        ->getJson('/api/settings/iku');

    $response->assertOk()
        ->assertJsonPath('iku.weights.registration', 10)
        ->assertJsonPath('iku.weights.lab_exam', 40)
        ->assertJsonPath('iku.weights.report', 40)
        ->assertJsonPath('iku.weights.survey', 10);
});

test('iku settings can be saved via api', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $payload = [
        'weights' => [
            'registration' => 15,
            'lab_exam' => 35,
            'report' => 35,
            'survey' => 15,
        ],
        'period_mode' => 'yearly',
        'target_samples_by_year' => [
            2025 => 150,
        ],
    ];

    $response = $this->actingAs($user)
        ->putJson('/api/settings/iku', $payload);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', 'Pengaturan IKU berhasil disimpan.')
        // Response includes updated config
        ->assertJsonPath('iku.weights.registration', 15)
        ->assertJsonPath('iku.weights.lab_exam', 35)
        ->assertJsonPath('iku.period_mode', 'yearly');
});

test('iku preview returns computation result', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->getJson('/api/settings/iku/preview');

    $response->assertOk()
        ->assertJsonStructure([
            'ok',
            'iku' => [
                'iku_value',
                'iku_category',
                'components' => ['R', 'P', 'L', 'S'],
            ],
        ]);
});

// =========================================
// IKU Computation Detail Tests
// =========================================

test('iku computation breakdown is accurate', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Set target for current year
    SystemSetting::updateOrCreate(
        ['key' => 'iku'],
        ['value' => [
            'weights' => [
                'registration' => 10,
                'lab_exam' => 40,
                'report' => 40,
                'survey' => 10,
            ],
            'period_mode' => 'monthly',
            'target_samples_by_year' => [
                ['year' => (int) date('Y'), 'target' => 100],
            ],
        ]]
    );
    settings_forget_cache();

    $service = app(IkuService::class);
    $result = $service->computeForCurrentMonth();

    expect($result)->toHaveKeys(['iku_value', 'iku_category', 'components']);
    expect($result['iku_value'])->toBeFloat();
    expect($result['iku_value'])->toBeGreaterThanOrEqual(0);
    expect($result['iku_value'])->toBeLessThanOrEqual(5);
    expect($result['iku_category'])->toBeIn(['A', 'B', 'C', 'D', 'E', 'F']);
});

test('iku handles edge case of all zero data', function () {
    $user = User::factory()->create(['role' => 'admin']);

    SystemSetting::updateOrCreate(
        ['key' => 'iku'],
        ['value' => [
            'weights' => [
                'registration' => 10,
                'lab_exam' => 40,
                'report' => 40,
                'survey' => 10,
            ],
            'period_mode' => 'monthly',
            'target_samples_by_year' => [],
        ]]
    );
    settings_forget_cache();

    $service = app(IkuService::class);
    $result = $service->computeForCurrentMonth();

    expect($result['iku_value'])->toBe(0.0);
    expect($result['iku_category'])->toBe('F');
});

// =========================================
// Survey Export Tests (from IKU Settings)
// =========================================

test('survey export link is accessible from settings page', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Check that the settings page renders without error
    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk()
        ->assertSee('Export Rekap Survey', false);
});

// =========================================
// IKU Dashboard Integration Tests
// =========================================

test('dashboard shows iku performance card', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('IKU Performance', false);
});

test('dashboard iku data is passed to view', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    // Check that iku_data is passed to view (using stats array)
    expect($response->viewData('stats'))->toHaveKeys(['iku_value', 'iku_category']);
});

test('dashboard iku value is within valid range', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $stats = $response->viewData('stats');
    expect($stats['iku_value'])->toBeGreaterThanOrEqual(0);
    expect($stats['iku_value'])->toBeLessThanOrEqual(5);
});

// =========================================
// Settings Page JavaScript Initialization Tests
// =========================================

test('settings page includes alpine x-data attribute', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk();
    // Verify that the Alpine component script reference exists
    $content = $response->getContent();
    expect($content)->toContain('x-data');
});

test('settings page loads correctly with custom iku config', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Set custom IKU config
    SystemSetting::updateOrCreate(
        ['key' => 'iku'],
        ['value' => [
            'weights' => [
                'registration' => 20,
                'lab_exam' => 30,
                'report' => 30,
                'survey' => 20,
            ],
            'period_mode' => 'yearly',
            'target_samples_by_year' => [
                ['year' => 2024, 'target' => 200],
                ['year' => 2025, 'target' => 300],
            ],
        ]]
    );
    settings_forget_cache();

    $response = $this->actingAs($user)
        ->get(route('settings.index'));

    $response->assertOk();
});

test('iku settings accepts quarterly period mode', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $payload = [
        'weights' => [
            'registration' => 15,
            'lab_exam' => 35,
            'report' => 35,
            'survey' => 15,
        ],
        'period_mode' => 'quarterly',
        'target_samples_by_year' => [
            2025 => 150,
        ],
    ];

    $response = $this->actingAs($user)
        ->putJson('/api/settings/iku', $payload);

    $response->assertOk()
        ->assertJsonPath('iku.period_mode', 'quarterly');
});

test('partial weight updates are checked against the saved total', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->putJson('/api/settings/iku', [
            'weights' => ['registration' => 20],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('weights');
});

test('iku quarterly target divides annual target by 4', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Set target for current year using exact keys IkuService expects
    SystemSetting::updateOrCreate(
        ['key' => 'iku.period_mode'],
        ['value' => 'monthly']
    );

    SystemSetting::updateOrCreate(
        ['key' => 'iku.target_samples_by_year'],
        ['value' => [(string) date('Y') => 200]]
    );

    settings_forget_cache();

    $service = app(IkuService::class);
    $result = $service->computeForPeriod(
        \Carbon\Carbon::parse(date('Y').'-07-01'),
        \Carbon\Carbon::parse(date('Y').'-09-30'),
        'quarterly'
    );

    // The report period, not the dashboard setting, selects the quarterly target.
    expect($result['raw_counts']['D'])->toBe(50);
    expect($result['weights'])->toBe(IkuService::DEFAULT_WEIGHTS);
});

test('request work count prefers event timestamps and falls back only when both events are absent', function () {
    TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'created_at' => '2026-07-10 08:00:00',
        'submitted_at' => '2026-07-10 08:00:00',
        'completed_at' => null,
        'ready_for_delivery_at' => '2026-08-10 08:00:00',
        'updated_at' => '2026-10-01 08:00:00',
    ]);
    TestRequest::factory()->create([
        'status' => 'completed',
        'created_at' => '2026-07-12 08:00:00',
        'submitted_at' => '2026-07-12 08:00:00',
        'completed_at' => null,
        'ready_for_delivery_at' => null,
        'updated_at' => '2026-08-12 08:00:00',
    ]);
    TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
        'created_at' => '2026-06-10 08:00:00',
        'submitted_at' => '2026-06-10 08:00:00',
        'completed_at' => null,
        'ready_for_delivery_at' => '2026-06-20 08:00:00',
        'updated_at' => '2026-08-15 08:00:00',
    ]);

    $result = app(IkuService::class)->computeForPeriod(
        \Carbon\Carbon::parse('2026-07-01'),
        \Carbon\Carbon::parse('2026-09-30')
    );

    expect($result['raw_counts']['A'])->toBe(2);
});

test('sample work count prefers interpretation events and uses updated time only for legacy records', function () {
    $eventRequest = TestRequest::factory()->create();
    $eventSample = Sample::factory()->create([
        'test_request_id' => $eventRequest->id,
        'sample_status' => 'ready_for_delivery',
        'testing_completed_at' => null,
        'updated_at' => '2026-10-01 08:00:00',
    ]);
    SampleTestProcess::query()->create([
        'sample_id' => $eventSample->id,
        'stage' => 'interpretation',
        'started_at' => '2026-08-19 08:00:00',
        'completed_at' => '2026-08-20 08:00:00',
    ]);

    $legacyRequest = TestRequest::factory()->create();
    Sample::factory()->create([
        'test_request_id' => $legacyRequest->id,
        'sample_status' => 'tested',
        'testing_completed_at' => null,
        'updated_at' => '2026-08-21 08:00:00',
    ]);

    $outOfPeriodRequest = TestRequest::factory()->create();
    $outOfPeriodSample = Sample::factory()->create([
        'test_request_id' => $outOfPeriodRequest->id,
        'sample_status' => 'tested',
        'testing_completed_at' => null,
        'updated_at' => '2026-08-22 08:00:00',
    ]);
    SampleTestProcess::query()->create([
        'sample_id' => $outOfPeriodSample->id,
        'stage' => 'interpretation',
        'started_at' => '2026-06-19 08:00:00',
        'completed_at' => '2026-06-20 08:00:00',
    ]);

    $result = app(IkuService::class)->computeForPeriod(
        \Carbon\Carbon::parse('2026-07-01'),
        \Carbon\Carbon::parse('2026-09-30')
    );

    expect($result['raw_counts']['C'])->toBe(2);
});
