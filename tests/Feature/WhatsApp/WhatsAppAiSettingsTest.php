<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('manage-settings', fn () => true);
});

it('saves ai configuration from whatsapp settings', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/whatsapp/settings', [
        'base_url' => 'https://gowa.local',
        'basic_user' => 'bot',
        'basic_pass' => 'secret-pass',
        'device_id' => 'device-123',
        'inventory_alert_expiry_days' => 30,
        'ai_provider' => 'openrouter',
        'ai_base_url' => 'https://openrouter.ai/api/v1/',
        'ai_model' => 'openrouter/auto',
        'ai_api_key' => 'or-secret-key',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('settings', [
        'key' => 'notifications.whatsapp.ai.provider',
    ]);

    $this->assertDatabaseHas('settings', [
        'key' => 'notifications.whatsapp.ai.base_url',
    ]);

    $this->assertDatabaseHas('settings', [
        'key' => 'notifications.whatsapp.ai.model',
    ]);

    expect(DB::table('settings')->where('key', 'notifications.whatsapp.ai.provider')->value('value'))->toBe('"openrouter"');
    expect(DB::table('settings')->where('key', 'notifications.whatsapp.ai.base_url')->value('value'))->toBe('"https://openrouter.ai/api/v1"');
    expect(DB::table('settings')->where('key', 'notifications.whatsapp.ai.model')->value('value'))->toBe('"openrouter/auto"');

    if (function_exists('settings_forget_cache')) {
        settings_forget_cache();
    }

    $encryptedKey = settings('notifications.whatsapp.ai.api_key');
    expect(is_string($encryptedKey))->toBeTrue();
    expect($encryptedKey)->not->toBe('or-secret-key');
    expect(decrypt((string) $encryptedKey))->toBe('or-secret-key');

    $settingsResponse = $this->actingAs($user)->getJson('/whatsapp/settings');
    $settingsResponse->assertOk()
        ->assertJson([
            'ai_provider' => 'openrouter',
            'ai_base_url' => 'https://openrouter.ai/api/v1',
            'ai_model' => 'openrouter/auto',
            'ai_api_key_configured' => true,
        ]);
});

it('tests ai from whatsapp settings endpoint', function () {
    /** @var \Tests\TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Respons AI test berhasil.',
                    ],
                ],
            ],
        ], 200),
    ]);

    config()->set('services.ai.base_url', 'https://api.openai.com/v1');
    config()->set('services.ai.key', 'test-key');
    config()->set('services.ai.model', 'gpt-4o-mini');

    $response = $this->actingAs($user)->postJson('/whatsapp/settings/test-ai', [
        'prompt' => 'Buat pesan test.',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'result' => 'Respons AI test berhasil.',
    ]);
});
