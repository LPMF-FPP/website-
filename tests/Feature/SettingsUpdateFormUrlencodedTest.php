<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('settings.update accepts form-url-encoded and flattens keys', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Simulate typical form-urlencoded payload with nested fields
    $payload = [
        'locale' => [
            'timezone' => 'Asia/Jakarta',
            'date_format' => 'YYYY-MM-DD',
        ],
        'branding' => [
            'theme' => 'dark',
        ],
    ];

    $this->actingAs($user)
        ->withoutMiddleware() // bypass auth/gate for the purpose of this feature test
        ->post(route('settings.update'), $payload)
        ->assertOk()
        ->assertJson(['ok' => true]);

    // Refresh settings cache and assert flattened keys are persisted
    settings_forget_cache();

    expect(settings('locale.timezone'))->toEqual('Asia/Jakarta');
    expect(settings('locale.date_format'))->toEqual('YYYY-MM-DD');
    expect(settings('branding.theme'))->toEqual('dark');

    // Also assert database rows exist for the flattened keys
    expect(SystemSetting::where('key', 'locale.timezone')->exists())->toBeTrue();
    expect(SystemSetting::where('key', 'locale.date_format')->exists())->toBeTrue();
    expect(SystemSetting::where('key', 'branding.theme')->exists())->toBeTrue();
});
