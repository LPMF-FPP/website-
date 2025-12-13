<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function postWithEmptyJsonAndForm($url, array $form)
{
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'Accept' => 'application/json',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ];
    // Attach form as query string so request->all() picks it up even when JSON content exists
    $qs = http_build_query($form);
    $uri = $qs ? ($url . (str_contains($url, '?') ? '&' : '?') . $qs) : $url;
    return test()->call('POST', $uri, [], [], [], $server, '[]');
}

test('extractPayload falls back to request->all() when JSON body is empty array', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $payload = [
        'locale' => [
            'timezone' => 'Asia/Jakarta',
            'date_format' => 'DD-MM-YYYY',
        ],
    ];

    $this->actingAs($user)->withoutMiddleware();
    $response = postWithEmptyJsonAndForm(route('settings.update'), $payload);

    // Using helper to unwrap the response
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['ok'] ?? false)->toBeTrue();

    settings_forget_cache();
    expect(settings('locale.timezone'))->toEqual('Asia/Jakarta');
    expect(settings('locale.date_format'))->toEqual('DD-MM-YYYY');
});

test('extractPayload unwraps nested settings when JSON body is empty array', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $payload = [
        'settings' => [
            'branding' => [
                'theme' => 'dark',
            ],
        ],
    ];

    $this->actingAs($user)->withoutMiddleware();
    $response = postWithEmptyJsonAndForm(route('settings.update'), $payload);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true)['ok'] ?? false)->toBeTrue();

    settings_forget_cache();
    expect(settings('branding.theme'))->toEqual('dark');
    expect(SystemSetting::where('key', 'branding.theme')->exists())->toBeTrue();
});
