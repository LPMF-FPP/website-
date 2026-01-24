<?php

declare(strict_types=1);

use App\Models\SystemSetting;
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

test('iku quarterly target divides annual target by 4', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Set target for current year using exact keys IkuService expects
    SystemSetting::updateOrCreate(
        ['key' => 'iku.period_mode'],
        ['value' => 'quarterly']
    );

    SystemSetting::updateOrCreate(
        ['key' => 'iku.target_samples_by_year'],
        ['value' => [(string) date('Y') => 200]]
    );

    settings_forget_cache();

    $service = app(IkuService::class);
    $result = $service->computeForCurrentQuarter();

    // D = target samples. Should be 200 / 4 = 50 for quarterly
    expect($result['raw_counts']['D'])->toBe(50);
});
