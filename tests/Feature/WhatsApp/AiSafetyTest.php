<?php

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AI\AiCommsService;
use App\Services\AI\AiConfiguration;
use App\Services\AI\AiEndpointPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class, \Tests\Concerns\UsesApprovedAiVerificationDatabase::class);

beforeEach(function () {
    expect(config('database.connections.pgsql.database'))->toBe('lpmf_release_20261013_verify');
    config(['services.ai.key' => 'neutral-secret', 'services.ai.base_url' => 'https://api.openai.com/v1', 'services.ai.model' => 'gpt-6-luna', 'services.ai.lock_store' => 'array']);
    config(['services.ai.base_url_configured' => false, 'services.ai.model_configured' => false]);
    Gate::define('manage-settings', fn () => true);
    $this->partialMock(AiEndpointPolicy::class)->shouldReceive('resolve')->andReturn(['8.8.8.8']);
    Http::preventStrayRequests();
    $this->actor = User::factory()->create();
});

function aiCompletion(mixed $content = 'Salam untuk uji netral.', mixed $finish = 'stop'): array
{
    return ['choices' => [['message' => ['content' => $content], 'finish_reason' => $finish]]];
}

it('rejects unapproved or ambiguous endpoints without any request', function (string $url) {
    settings_fake(['notifications.whatsapp.ai.base_url' => $url]);
    Http::fake();
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji netral'])->assertStatus(422)->assertJsonPath('code', 'AI_CONFIGURATION_INVALID');
    Http::assertNothingSent();
})->with([
    'http://api.openai.com/v1', 'https://unknown.example/v1', 'https://api.openai.com.evil.example/v1',
    'https://key@api.openai.com/v1', 'https://api.openai.com:444/v1', 'https://127.0.0.1/v1',
    'https://api.openai.com/v1?redirect=x', 'https://api.openai.com/v1#fragment', 'https://api.openai.com/v1/../other',
]);

it('rejects every private or reserved DNS answer including mixed answers', function (array $addresses) {
    $this->instance(AiEndpointPolicy::class, new class($addresses) extends AiEndpointPolicy
    {
        public function __construct(private array $addresses) {}

        public function resolve(string $host): array
        {
            return $this->addresses;
        }
    });
    Http::fake();
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertStatus(422)->assertJsonPath('code', 'AI_DESTINATION_INVALID');
    Http::assertNothingSent();
})->with([[[]], [['127.0.0.1']], [['10.0.0.1']], [['169.254.169.254']], [['100.64.0.1']], [['192.0.2.1']], [['::1']], [['::ffff:127.0.0.1']], [['2001:db8::1']], [['fc00::1']], [['8.8.8.8', '192.168.0.1']]]);

it('pins validated addresses and forbids redirects proxies and insecure TLS', function () {
    $options = app(AiEndpointPolicy::class)->connectionOptions('https://api.openai.com/v1');
    expect($options)->toMatchArray(['allow_redirects' => false, 'verify' => true, 'proxy' => '']);
    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['api.openai.com:443:8.8.8.8']);
});

it('never transmits persisted ciphertext or falls back after decryption failure', function () {
    settings_fake(['notifications.whatsapp.ai.api_key' => 'invalid-ciphertext']);
    Http::fake();
    $response = $this->actingAs($this->actor)->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Uji']);
    $response->assertStatus(422)->assertJsonPath('code', 'AI_CREDENTIALS_INVALID')->assertJsonStructure(['request_id']);
    expect($response->getContent())->not->toContain('invalid-ciphertext', 'neutral-secret');
    $this->getJson('/whatsapp/settings')->assertJsonPath('ai_api_key_configured', false);
    Http::assertNothingSent();
});

it('validates output text finish reason and server owned tokens', function (array $body, string $code) {
    Http::fake(['*' => Http::response($body)]);
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji', 'variables' => ['invented']])->assertStatus(503)->assertJsonPath('code', $code);
    Http::assertSentCount(1);
})->with([
    [aiCompletion(''), 'AI_OUTPUT_INVALID'], [aiCompletion('   '), 'AI_OUTPUT_INVALID'],
    [aiCompletion(null), 'AI_OUTPUT_INVALID'], [aiCompletion(['text' => 'x']), 'AI_OUTPUT_INVALID'],
    [aiCompletion('x', 'length'), 'AI_OUTPUT_INVALID'], [aiCompletion('x', null), 'AI_OUTPUT_INVALID'],
    [aiCompletion('x', 'content_filter'), 'AI_OUTPUT_INVALID'], [aiCompletion('x', 'tool_calls'), 'AI_OUTPUT_INVALID'],
    [['choices' => [['message' => ['content' => 'x', 'tool_calls' => [['id' => 'call-1']]], 'finish_reason' => 'stop']]], 'AI_OUTPUT_INVALID'],
    [['choices' => [['message' => ['content' => 'x', 'refusal' => 'Tidak dapat membantu'], 'finish_reason' => 'stop']]], 'AI_OUTPUT_INVALID'],
    [aiCompletion('<html>offline</html>'), 'AI_OUTPUT_INVALID'], [aiCompletion('{invented}'), 'AI_PLACEHOLDER_INVALID'],
    [aiCompletion('neutral-secret'), 'AI_OUTPUT_INVALID'], [[], 'AI_OUTPUT_INVALID'],
    [aiCompletion('{nama'), 'AI_PLACEHOLDER_INVALID'], [aiCompletion('{{nama}}'), 'AI_PLACEHOLDER_INVALID'],
]);

it('returns safe provider failures without raw bodies or inference retries', function (int $status, string $body, string $code, int $expected) {
    Log::spy();
    Http::fake(['*' => Http::response($body, $status)]);
    $response = $this->actingAs($this->actor)->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Neutral']);
    $response->assertStatus($expected)->assertJsonPath('code', $code)->assertJsonStructure(['request_id']);
    $response->assertJsonStructure(['success', 'error', 'message', 'code', 'request_id'])->assertJsonPath('success', false);
    expect($response->getContent())->not->toContain('neutral-secret', 'private-case-fixture');
    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => array_keys($context) === ['request_id', 'code']);
    Log::shouldNotHaveReceived('error');
    Http::assertSentCount(1);
})->with([
    [500, 'neutral-secret private-case-fixture', 'AI_SERVICE_UNAVAILABLE', 503],
    [429, 'neutral-secret', 'AI_PROVIDER_LIMIT', 429],
    [401, 'neutral-secret', 'AI_PROVIDER_AUTH_FAILED', 422],
    [302, 'neutral-secret', 'AI_REDIRECT_REJECTED', 422],
    [404, 'ERR_NGROK_3200 neutral-secret', 'AI_TUNNEL_OFFLINE', 503],
    [200, '<html>neutral-secret</html>', 'AI_OUTPUT_INVALID', 503],
    [200, '{invalid JSON', 'AI_OUTPUT_INVALID', 503],
]);

it('rejects provider bodies above the configured response limit', function () {
    config(['services.ai.max_response_bytes' => 1024]);
    Http::fake(['*' => Http::response(str_repeat('x', 2048), 200)]);

    $this->actingAs($this->actor)->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Uji netral'])
        ->assertStatus(503)->assertJsonPath('code', 'AI_OUTPUT_INVALID');
});

it('returns safe timeout and releases the concurrency lock', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('neutral-secret'));
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertStatus(503)->assertJsonPath('code', 'AI_TIMEOUT');
    $lock = Cache::store('array')->lock('ai:user:'.$this->actor->id, 30);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

it('logs only a safe exception type for unexpected provider transport failures', function () {
    Log::spy();
    Http::fake(fn () => throw new RuntimeException('private-case-fixture and neutral-secret'));

    $response = $this->actingAs($this->actor)->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Uji netral']);
    $response->assertStatus(503)->assertJsonPath('code', 'AI_SERVICE_UNAVAILABLE');
    expect($response->getContent())->not->toContain('private-case-fixture', 'neutral-secret');
    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => ($context['code'] ?? null) === 'AI_SERVICE_UNAVAILABLE'
        && ($context['exception'] ?? null) === RuntimeException::class
        && ! array_key_exists('message', $context));
});

it('uses provider defaults and preserves the configured proxy model without speculative payload parameters', function (string $provider, string $url, string $model) {
    settings_fake(['notifications.whatsapp.ai.provider' => $provider, 'notifications.whatsapp.ai.api_key' => encrypt('provider-key')]);
    Http::fake(['*' => Http::response(aiCompletion())]);
    app(AiCommsService::class)->generateMessage('Uji netral');
    Http::assertSent(fn ($request) => $request->url() === $url.'/chat/completions' && $request['model'] === $model && array_keys($request->data()) === ['model', 'messages']);
})->with([
    ['openai', 'https://api.openai.com/v1', 'gpt-6-luna'],
    ['openrouter', 'https://openrouter.ai/api/v1', 'openrouter/auto'],
    ['deepseek', 'https://api.deepseek.com/v1', 'deepseek-chat'],
]);

it('uses the approved ngrok endpoint and exact configured model', function () {
    settings_fake(['notifications.whatsapp.ai.provider' => 'custom', 'notifications.whatsapp.ai.base_url' => 'https://magan-nontelic-vincenza.ngrok-free.dev/v1', 'notifications.whatsapp.ai.model' => 'gpt-6-luna', 'notifications.whatsapp.ai.api_key' => encrypt('neutral-key')]);
    Http::fake(['*' => Http::response(aiCompletion())]);
    app(AiCommsService::class)->generateMessage('Salam uji netral');
    Http::assertSent(fn ($request) => $request->url() === 'https://magan-nontelic-vincenza.ngrok-free.dev/v1/chat/completions' && $request['model'] === 'gpt-6-luna');
});

it('preserves explicitly configured environment proxy defaults for a custom provider', function () {
    config(['services.ai.base_url_configured' => true, 'services.ai.model_configured' => true, 'services.ai.base_url' => 'https://magan-nontelic-vincenza.ngrok-free.dev/v1', 'services.ai.model' => 'gpt-6-luna']);
    settings_fake(['notifications.whatsapp.ai.provider' => 'custom']);
    Http::fake(['*' => Http::response(aiCompletion())]);
    app(AiCommsService::class)->generateMessage('Salam uji netral');
    Http::assertSent(fn ($request) => $request->url() === 'https://magan-nontelic-vincenza.ngrok-free.dev/v1/chat/completions' && $request['model'] === 'gpt-6-luna' && $request->header('Authorization')[0] === 'Bearer neutral-secret');
});

it('does not send an environment key to an overridden destination', function () {
    settings_fake(['notifications.whatsapp.ai.base_url' => 'https://openrouter.ai/api/v1']);
    Http::fake();
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertStatus(422)->assertJsonPath('code', 'AI_CREDENTIALS_INVALID');
    Http::assertNothingSent();
});

it('shares drafts only with explicit consent and uses countdown context tokens', function () {
    Http::fake(['*' => Http::response(aiCompletion('{event_name} {days_remaining} {motivation_message}'))]);
    $payload = ['prompt' => 'Uji', 'context_type' => 'iso_countdown', 'current_text' => '{event_name} {days_remaining} {motivation_message}'];
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', $payload)->assertOk();
    Http::assertSent(fn ($request) => count($request['messages']) === 2 && str_contains($request['messages'][0]['content'], '{motivation_message}') && ! str_contains($request['messages'][0]['content'], '{nama}'));
    $this->postJson('/whatsapp/ai/compose', [...$payload, 'share_draft' => true])->assertOk();
    Http::assertSent(fn ($request) => count($request['messages']) === 3
        && $request['messages'][1]['content'] === 'Current draft: '.$payload['current_text']
        && $request['messages'][2]['content'] === $payload['prompt']);
});

it('rejects dropped countdown variables and cross-context tokens', function () {
    Http::fake(['*' => Http::response(aiCompletion('{event_name}'))]);
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji', 'context_type' => 'countdown', 'share_draft' => true, 'current_text' => '{event_name} {milestone_message}'])->assertStatus(503)->assertJsonPath('code', 'AI_PLACEHOLDER_INVALID');
    $this->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji', 'context_type' => 'task'])->assertStatus(503)->assertJsonPath('code', 'AI_PLACEHOLDER_INVALID');
});

it('preserves repeated countdown placeholder occurrences', function () {
    Http::fake(['*' => Http::response(aiCompletion('{event_name}'))]);

    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', [
        'prompt' => 'Uji netral',
        'context_type' => 'countdown',
        'share_draft' => true,
        'current_text' => '{event_name} dan {event_name}',
    ])->assertStatus(503)->assertJsonPath('code', 'AI_PLACEHOLDER_INVALID');
});

it('preserves allowed placeholders from explicitly shared non-countdown drafts', function () {
    $token = app(\App\Services\AI\AiPlaceholderCatalog::class)->keys('task')[0];
    Http::fake(['*' => Http::response(aiCompletion('Pesan netral.'))]);

    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', [
        'prompt' => 'Uji netral',
        'context_type' => 'task',
        'share_draft' => true,
        'current_text' => '{'.$token.'}',
    ])->assertStatus(503)->assertJsonPath('code', 'AI_PLACEHOLDER_INVALID');
});

it('holds the shared configuration lock while provider inference is in progress', function () {
    $actor = $this->actor;
    $failure = null;
    Http::fake(function () use ($actor, &$failure) {
        try {
            app(\App\Services\AI\AiSettingsService::class)->save([
                'ai_provider' => 'openai',
                'ai_base_url' => 'https://api.openai.com/v1',
                'ai_model' => 'changed-model',
                'ai_api_key' => 'neutral-key',
            ], $actor);
        } catch (\App\Services\AI\AiFailure $error) {
            $failure = $error;
        }

        return Http::response(aiCompletion());
    });

    $revision = app(AiConfiguration::class)->values()['revision'];
    $this->actingAs($actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji netral', 'expected_revision' => $revision])->assertOk();

    expect($failure)->toBeInstanceOf(\App\Services\AI\AiFailure::class)
        ->and($failure->errorCode)->toBe('AI_CONFIGURATION_BUSY')
        ->and(app(AiConfiguration::class)->values()['model'])->toBe('gpt-6-luna');
});

it('rejects a response from a changed configuration even without a client revision', function () {
    Http::fake(function () {
        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.ai.model'],
            ['value' => 'changed-model']
        );

        return Http::response(aiCompletion());
    });

    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji netral'])
        ->assertStatus(409)->assertJsonPath('code', 'AI_CONFIGURATION_CHANGED');
});

it('rejects guests forbidden inactive and unverified users before side effects', function () {
    Http::fake();
    $this->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertUnauthorized();
    Gate::define('manage-settings', fn () => false);
    foreach (['/whatsapp/ai/compose', '/whatsapp/settings/test-ai', '/whatsapp/settings/ai'] as $route) {
        $this->actingAs($this->actor)->postJson($route, ['prompt' => 'Uji'])->assertForbidden();
    }
    Http::assertNothingSent();
    expect(SystemSetting::where('key', 'like', 'notifications.whatsapp.ai.%')->count())->toBe(0);
    Gate::define('manage-settings', fn () => true);
    $inactive = User::factory()->create(['is_active' => false]);
    $this->actingAs($inactive)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertRedirect('/login');
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertForbidden();
    Http::assertNothingSent();
});

it('shares rate and concurrency controls across compose and test', function () {
    Http::fake(['*' => Http::response(aiCompletion())]);
    config(['services.ai.rate_limit' => 2]);
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertOk();
    $this->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Uji'])->assertOk();
    $this->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertStatus(429)->assertJsonPath('code', 'AI_RATE_LIMIT');
    Http::assertSentCount(2);
});

it('fails closed when a production limiter uses a non-shared cache store', function () {
    $originalEnvironment = app()->environment();
    config(['cache.default' => 'array', 'services.ai.lock_store' => 'database']);
    app()['env'] = 'production';
    $request = \Illuminate\Http\Request::create('/whatsapp/ai/compose', 'POST');
    $request->setUserResolver(fn () => $this->actor);

    try {
        $response = (new \App\Http\Middleware\LimitAiRequests)->handle($request, fn () => response('unexpected'));
        expect($response->getStatusCode())->toBe(503)
            ->and($response->getData(true)['code'])->toBe('AI_LIMITS_UNAVAILABLE');
    } finally {
        app()['env'] = $originalEnvironment;
    }
});

it('rejects a concurrent call and configurable exhausted global budget without sending', function () {
    Http::fake();
    $lock = Cache::store('array')->lock('ai:user:'.$this->actor->id, 30);
    $lock->get();
    $this->actingAs($this->actor)->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Uji'])->assertStatus(429)->assertJsonPath('code', 'AI_BUSY');
    $lock->release();
    config(['services.ai.global_budget' => 0]);
    $this->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertStatus(429)->assertJsonPath('code', 'AI_BUDGET_LIMIT');
    Http::assertNothingSent();
});

it('honors the shared database lock store and releases ownership safely', function () {
    config(['services.ai.lock_store' => 'database']);
    config(['cache.stores.database.connection' => 'pgsql', 'cache.stores.database.lock_connection' => 'pgsql']);
    // PostgreSQL aborts an enclosing test transaction on Laravel's duplicate-lock INSERT probe.
    // Exercise the production autocommit path, then remove this isolated test's actor explicitly.
    DB::commit();
    Http::fake(['*' => Http::response(aiCompletion())]);
    $lock = Cache::store('database')->lock('ai:user:'.$this->actor->id, 30);
    try {
        expect($lock->get())->toBeTrue();
        $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertStatus(429)->assertJsonPath('code', 'AI_BUSY');
        $lock->release();
        $this->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Uji'])->assertOk();
        Http::assertSentCount(1);
    } finally {
        $lock->release();
        $this->actor->forceDelete();
    }
});

it('shares the global inference budget across different users and both consumers', function () {
    $this->freezeTime();
    config(['services.ai.global_budget' => 1]);
    Http::fake(['*' => Http::response(aiCompletion())]);
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji'])->assertOk();
    $anotherActor = User::factory()->create();
    $this->actingAs($anotherActor)->postJson('/whatsapp/settings/test-ai', ['prompt' => 'Uji'])->assertStatus(429)->assertJsonPath('code', 'AI_BUDGET_LIMIT');
    Http::assertSentCount(1);
});

it('validates before reserving the shared paid-inference budget', function () {
    config(['services.ai.global_budget' => 0]);
    settings_fake(['notifications.whatsapp.ai.base_url' => 'http://invalid.example/v1']);
    Http::fake();

    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji netral'])
        ->assertStatus(422)->assertJsonPath('code', 'AI_CONFIGURATION_INVALID');
    Http::assertNothingSent();
});

it('saves AI alone preserves omitted blank and masked keys and never exposes secrets in audit or responses', function (mixed $replacement) {
    SystemSetting::updateOrCreate(['key' => 'notifications.whatsapp.base_url'], ['value' => 'https://gowa.local']);
    SystemSetting::create(['key' => 'notifications.whatsapp.ai.api_key', 'value' => encrypt('stored-secret')]);
    $payload = ['ai_provider' => 'openai', 'ai_model' => 'gpt-6-luna', 'ai_base_url' => 'https://api.openai.com/v1'];
    if ($replacement !== null) {
        $payload['ai_api_key'] = $replacement;
    }
    $response = $this->actingAs($this->actor)->postJson('/whatsapp/settings/ai', $payload);
    $response->assertOk()->assertJsonPath('ai_api_key_configured', true);
    expect(app(AiConfiguration::class)->values()['key'])->toBe('stored-secret');
    expect(settings('notifications.whatsapp.base_url'))->toBe('https://gowa.local');
    $ciphertext = SystemSetting::where('key', 'notifications.whatsapp.ai.api_key')->first()->value;
    expect($response->getContent())->not->toContain('stored-secret', $ciphertext);
    expect(ActivityLog::all()->toJson())->not->toContain('stored-secret', $ciphertext);
})->with([null, '', '••••••••', '********']);

it('never includes key fields or ciphertext in the WhatsApp settings read response', function () {
    $ciphertext = encrypt('neutral-settings-secret');
    SystemSetting::create(['key' => 'notifications.whatsapp.ai.api_key', 'value' => $ciphertext]);

    $response = $this->actingAs($this->actor)->getJson('/whatsapp/settings')->assertOk();

    expect(array_key_exists('ai_api_key', $response->json()))->toBeFalse();
    expect($response->getContent())->not->toContain('neutral-settings-secret', $ciphertext);
});

it('requires provider destination confirmation and rejects partial or conflicting settings atomically', function (array $payload) {
    $this->actingAs($this->actor)->postJson('/whatsapp/settings/ai', $payload)->assertStatus(422);
    expect(SystemSetting::where('key', 'like', 'notifications.whatsapp.ai.%')->count())->toBe(0);
})->with([
    [['ai_provider' => 'custom', 'ai_api_key' => 'new-key', 'ai_confirm_destination' => true]],
    [['ai_provider' => 'deepseek', 'ai_api_key' => 'new-key']],
    [['ai_provider' => 'deepseek', 'ai_confirm_destination' => true]],
    [['ai_provider' => 'openai', 'ai_remove_key' => true, 'ai_api_key' => 'new-key']],
    [['ai_provider' => 'openai', 'ai_reset_overrides' => true, 'ai_model' => 'conflict']],
]);

it('rejects stale AI settings saves without overwriting newer configuration', function () {
    $revision = app(AiConfiguration::class)->values()['revision'];
    $this->actingAs($this->actor)->postJson('/whatsapp/settings/ai', [
        'ai_provider' => 'openai',
        'ai_base_url' => 'https://api.openai.com/v1',
        'ai_model' => 'gpt-6-luna',
        'ai_api_key' => 'neutral-new-key',
        'expected_revision' => $revision,
    ])->assertOk();

    $this->postJson('/whatsapp/settings/ai', [
        'ai_provider' => 'openai',
        'ai_base_url' => 'https://api.openai.com/v1',
        'ai_model' => 'stale-model',
        'expected_revision' => $revision,
    ])->assertStatus(409)->assertJsonPath('code', 'AI_CONFIGURATION_CHANGED');

    expect(app(AiConfiguration::class)->values()['model'])->toBe('gpt-6-luna');
});

it('resets overrides to authoritative defaults and explicit removal disables environment fallback', function () {
    SystemSetting::create(['key' => 'notifications.whatsapp.ai.model', 'value' => 'old-model']);
    $this->actingAs($this->actor)->postJson('/whatsapp/settings/ai', ['ai_provider' => 'openai', 'ai_reset_overrides' => true])->assertOk()->assertJsonPath('ai_model', 'gpt-6-luna');
    $this->postJson('/whatsapp/settings/ai', ['ai_provider' => 'openai', 'ai_remove_key' => true])->assertOk()->assertJsonPath('ai_api_key_configured', false);
    expect(app(AiConfiguration::class)->values()['key'])->toBeNull();
});

it('can remove a credential from an invalid endpoint without changing that endpoint or model', function () {
    SystemSetting::create(['key' => 'notifications.whatsapp.ai.base_url', 'value' => 'http://invalid.local/v1']);
    SystemSetting::create(['key' => 'notifications.whatsapp.ai.api_key', 'value' => encrypt('removable-key')]);
    $this->actingAs($this->actor)->postJson('/whatsapp/settings/ai', ['ai_provider' => 'openai', 'ai_remove_key' => true])->assertOk()->assertJsonPath('ai_api_key_configured', false);
    expect(SystemSetting::where('key', 'notifications.whatsapp.ai.base_url')->first()->value)->toBe('http://invalid.local/v1');
});

it('rolls back all settings and audit on persistence failure without disclosing key material', function () {
    SystemSetting::saving(function ($setting) {
        if ($setting->key === 'notifications.whatsapp.ai.model') {
            throw new RuntimeException('secret persistence detail');
        }
    });
    try {
        $response = $this->actingAs($this->actor)->postJson('/whatsapp/settings/ai', ['ai_provider' => 'openai', 'ai_api_key' => 'new-secret', 'ai_model' => 'new-model']);
        $response->assertStatus(500)->assertJsonPath('code', 'AI_SETTINGS_FAILED');
        expect($response->getContent())->not->toContain('new-secret', 'secret persistence detail');
        expect(SystemSetting::where('key', 'like', 'notifications.whatsapp.ai.%')->count())->toBe(0);
        expect(ActivityLog::count())->toBe(0);
    } finally {
        \Illuminate\Support\Facades\Event::forget('eloquent.saving: '.SystemSetting::class);
    }
});

it('does not publish AI cache invalidation from an uncommitted transaction', function () {
    Cache::put('sys_settings_all', ['committed' => true]);
    DB::beginTransaction();
    SystemSetting::create(['key' => 'notifications.whatsapp.ai.model', 'value' => 'uncommitted']);
    expect(Cache::get('sys_settings_all'))->toBe(['committed' => true]);
    DB::rollBack();
    expect(Cache::get('sys_settings_all'))->toBe(['committed' => true]);
});

it('rejects stale configuration revision without calling provider', function () {
    Http::fake();
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji', 'expected_revision' => 'outdated'])->assertStatus(409)->assertJsonPath('code', 'AI_CONFIGURATION_CHANGED');
    Http::assertNothingSent();
});

it('invalidates a legacy revision when the environment destination or model changes', function () {
    $revision = app(AiConfiguration::class)->values()['revision'];
    config(['services.ai.base_url' => 'https://magan-nontelic-vincenza.ngrok-free.dev/v1']);
    Http::fake();
    $this->actingAs($this->actor)->postJson('/whatsapp/ai/compose', ['prompt' => 'Uji', 'expected_revision' => $revision])->assertStatus(409);
    Http::assertNothingSent();
});

it('excludes AI credentials and payloads from Telescope diagnostic entries', function (array $content) {
    $entry = \Laravel\Telescope\IncomingEntry::make($content);
    expect(\App\Services\AI\AiTelemetryPrivacy::allowsRecord($entry))->toBeFalse();
    expect(\Laravel\Telescope\Telescope::$hiddenRequestParameters)->toContain('ai_api_key', 'current_text', 'prompt');
    expect(\Laravel\Telescope\Telescope::$hiddenRequestHeaders)->toContain('authorization');
})->with([
    [['uri' => '/whatsapp/settings/ai', 'payload' => ['ai_api_key' => 'neutral-secret']]],
    [['uri' => '/whatsapp/ai/compose', 'payload' => ['current_text' => 'neutral draft']]],
    [['uri' => '/whatsapp/settings/test-ai', 'payload' => ['prompt' => 'neutral']]],
    [['uri' => 'https://magan-nontelic-vincenza.ngrok-free.dev/v1/chat/completions', 'headers' => ['authorization' => 'Bearer neutral-secret']]],
    [['model' => SystemSetting::class.':1', 'changes' => ['value' => 'neutral-ciphertext']]],
    [['name' => 'eloquent.updated: '.SystemSetting::class, 'payload' => ['value' => 'neutral-ciphertext']]],
    [['key' => 'sys_settings_all', 'value' => ['api_key' => 'neutral-ciphertext']]],
    [['sql' => 'update "settings" set "value" = ?', 'bindings' => ['neutral-ciphertext']]],
]);

it('redacts secret-bearing settings and cache SQL but preserves unrelated diagnostic eligibility', function () {
    expect(\App\Services\AI\AiTelemetryPrivacy::sensitiveQuery('update "settings" set "value" = ?', ['neutral-ciphertext']))->toBeTrue();
    expect(\App\Services\AI\AiTelemetryPrivacy::sensitiveQuery('update "cache" set "value" = ?', ['serialized notifications.whatsapp.ai.api_key']))->toBeTrue();
    expect(\App\Services\AI\AiTelemetryPrivacy::allowsRecord(\Laravel\Telescope\IncomingEntry::make(['sql' => 'select * from "samples"'])))->toBeTrue();
});

it('never flashes a rejected replacement key into session old input', function () {
    $this->actingAs($this->actor)->post('/whatsapp/settings/ai', ['ai_provider' => 'unsupported', 'ai_api_key' => 'neutral-secret'])->assertRedirect()->assertSessionMissing('_old_input.ai_api_key');
});
