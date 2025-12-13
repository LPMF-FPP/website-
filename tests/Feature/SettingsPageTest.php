<?php

use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    SystemSetting::updateOrCreate(['key' => 'branding.lab_code'], ['value' => 'LPMF']);
    SystemSetting::updateOrCreate(['key' => 'numbering.sample_code.pattern'], ['value' => 'LPMF-{YYYY}{MM}-{INV}-{SEQ:4}']);
    SystemSetting::updateOrCreate(['key' => 'numbering.sample_code.reset'], ['value' => 'monthly']);
    SystemSetting::updateOrCreate(['key' => 'numbering.sample_code.start_from'], ['value' => 1]);
    SystemSetting::updateOrCreate(['key' => 'numbering.ba.pattern'], ['value' => 'BA/{YYYY}/{MM}/{SEQ:4}']);
    SystemSetting::updateOrCreate(['key' => 'numbering.ba.reset'], ['value' => 'monthly']);
    SystemSetting::updateOrCreate(['key' => 'numbering.ba.start_from'], ['value' => 1]);
    SystemSetting::updateOrCreate(['key' => 'numbering.lhu.pattern'], ['value' => 'LHU/{YYYY}/{MM}/{TEST}/{SEQ:4}']);
    SystemSetting::updateOrCreate(['key' => 'numbering.lhu.reset'], ['value' => 'monthly']);
    SystemSetting::updateOrCreate(['key' => 'numbering.lhu.start_from'], ['value' => 1]);
    SystemSetting::updateOrCreate(['key' => 'numbering.lhu.per_test_type'], ['value' => true]);

    settings_forget_cache();
});

test('settings page loads with nested data', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Pengaturan LIMS', false)
        ->assertSee('Penomoran Otomatis', false);
});

test('preview endpoint returns example number', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Setup complete numbering config for 'lhu' scope
    SystemSetting::updateOrCreate(
        ['key' => 'numbering'],
        ['value' => [
            'lhu' => [
                'pattern' => 'LHU/{YYYY}/{SEQ:5}',
                'reset' => 'monthly',
                'start_from' => 1,
            ]
        ]]
    );
    settings_forget_cache();

    $response = $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson(route('settings.preview'), [
            'scope' => 'lhu',
            'config' => ['numbering' => ['lhu' => ['pattern' => 'LHU/{YYYY}/{SEQ:5}']]],
        ])
        ->assertOk()
        ->assertJsonStructure(['example']);

    expect($response->json('example'))->toBeString()->not->toBe('');
});

test('settings update persists flattened values', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $payload = [
        'numbering' => [
            'ba' => [
                'pattern' => 'BA/{YYYY}/{SEQ:5}',
                'reset' => 'yearly',
                'start_from' => 9,
            ],
        ],
        'security' => [
            'roles' => [
                'can_manage_settings' => ['admin'],
                'can_issue_number' => ['admin', 'supervisor'],
            ],
        ],
    ];

    $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson(route('settings.update'), $payload)
        ->assertOk()
        ->assertJson(['ok' => true]);

    settings_forget_cache();

    expect(settings('numbering.ba.pattern'))->toEqual('BA/{YYYY}/{SEQ:5}');
    expect(settings('numbering.ba.reset'))->toEqual('yearly');
    expect(settings('numbering.ba.start_from'))->toEqual(9);
    expect(settings('security.roles.can_issue_number'))->toEqual(['admin', 'supervisor']);
});
