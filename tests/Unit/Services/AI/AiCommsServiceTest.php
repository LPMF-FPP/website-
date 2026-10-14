<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AiCommsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiCommsServiceTest extends TestCase
{
    use \Tests\Concerns\UsesApprovedAiVerificationDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.ai.base_url', 'https://api.openai.com/v1');
        Config::set('services.ai.key', 'test-key');
        Config::set('services.ai.model', 'gpt-3.5-turbo');
        Config::set('services.ai.lock_store', 'array');
        $this->partialMock(\App\Services\AI\AiEndpointPolicy::class)->shouldReceive('resolve')->andReturn(['8.8.8.8']);
    }

    public function test_generate_message_success()
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello WhatsApp',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $service = app(AiCommsService::class);
        $result = $service->generateMessage('Draft a message');

        $this->assertEquals('Hello WhatsApp', $result);
    }

    public function test_generate_message_success_with_trailing_slash_base_url()
    {
        Config::set('services.ai.base_url', 'https://api.openai.com/v1/');

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello WhatsApp',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $service = app(AiCommsService::class);
        $result = $service->generateMessage('Draft a message');

        $this->assertEquals('Hello WhatsApp', $result);
    }

    public function test_generate_message_api_error()
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response('Error', 500),
        ]);

        $this->expectException(RuntimeException::class);

        $service = app(AiCommsService::class);
        $service->generateMessage('Draft a message');
    }

    public function test_generate_message_includes_allowed_placeholders_in_system_prompt()
    {
        $method = new \ReflectionMethod(AiCommsService::class, 'generateMessage');
        $this->assertGreaterThanOrEqual(
            4,
            $method->getNumberOfParameters(),
            'generateMessage must accept an allowed placeholder variables parameter.'
        );

        Http::preventStrayRequests();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => function ($request) {
                $this->assertSame('https://api.openai.com/v1/chat/completions', $request->url());

                $payload = $request->data();
                $systemPrompt = $payload['messages'][0]['content'] ?? '';

                $this->assertIsString($systemPrompt);
                $this->assertStringContainsString('{nama}', $systemPrompt);
                $this->assertStringContainsString('{nomor surat}', $systemPrompt);
                $this->assertStringNotContainsString('{priority}', $systemPrompt);
                $this->assertStringNotContainsString('{x/y}', $systemPrompt);
                $this->assertStringNotContainsString('{bad*}', $systemPrompt);
                $this->assertStringNotContainsString('{x\\y}', $systemPrompt);
                $this->assertSame(1, substr_count($systemPrompt, '{nama}'));
                $this->assertStringContainsStringIgnoringCase('only', $systemPrompt);
                $this->assertStringContainsStringIgnoringCase('do not invent', $systemPrompt);
                $this->assertStringContainsString('*bold*', $systemPrompt);
                $this->assertStringContainsString('professional emojis sparingly', $systemPrompt);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Hello WhatsApp',
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = app(AiCommsService::class);
        $result = $service->generateMessage('Draft a message', null, 'general', [
            ' nama ',
            '{nomor surat}',
            'priority',
            'priority',
            '',
            '{}',
            '{bad*}',
            'bad*',
            'x/y',
            'x\\y',
        ]);

        $this->assertEquals('Hello WhatsApp', $result);
    }

    public function test_missing_api_key()
    {
        Config::set('services.ai.key', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kunci atau model AI belum siap.');

        $service = app(AiCommsService::class);
        $service->generateMessage('Draft a message');
    }

    public function test_generate_message_uses_whatsapp_ai_settings_override()
    {
        settings_fake([
            'notifications.whatsapp.ai.provider' => 'openrouter',
            'notifications.whatsapp.ai.base_url' => 'https://openrouter.ai/api/v1',
            'notifications.whatsapp.ai.model' => 'openrouter/auto',
            'notifications.whatsapp.ai.api_key' => encrypt('settings-key'),
        ], true);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => function ($request) {
                $this->assertSame('https://openrouter.ai/api/v1/chat/completions', $request->url());
                $this->assertSame('Bearer settings-key', $request->header('Authorization')[0] ?? null);
                $this->assertSame('openrouter/auto', $request->data()['model'] ?? null);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Override OK',
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = app(AiCommsService::class);
        $result = $service->generateMessage('Draft a message');

        $this->assertSame('Override OK', $result);
    }

    public function test_generate_message_applies_endpoint_policy_to_transport_request(): void
    {
        $options = [
            'allow_redirects' => false,
            'verify' => true,
            'proxy' => '',
            'curl' => [CURLOPT_RESOLVE => ['api.openai.com:443:8.8.8.8'], CURLOPT_PROXY => ''],
        ];
        $response = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['choices' => [['message' => ['content' => 'Salam netral'], 'finish_reason' => 'stop']]], JSON_THROW_ON_ERROR)
        ));
        $pending = \Mockery::mock(\Illuminate\Http\Client\PendingRequest::class);
        $pending->shouldReceive('acceptJson')->once()->andReturnSelf();
        $pending->shouldReceive('asJson')->once()->andReturnSelf();
        $pending->shouldReceive('withToken')->once()->with('test-key')->andReturnSelf();
        $pending->shouldReceive('connectTimeout')->once()->andReturnSelf();
        $pending->shouldReceive('timeout')->once()->andReturnSelf();
        $pending->shouldReceive('post')->once()->with('https://api.openai.com/v1/chat/completions', \Mockery::type('array'))->andReturn($response);
        $factory = \Mockery::mock(\Illuminate\Http\Client\Factory::class);
        $factory->shouldReceive('withOptions')->once()->with(\Mockery::on(fn (array $actual): bool => $actual['allow_redirects'] === $options['allow_redirects']
            && $actual['verify'] === $options['verify']
            && $actual['proxy'] === $options['proxy']
            && $actual['curl'] === $options['curl']
            && isset($actual['on_headers'], $actual['progress'])
        ))->andReturn($pending);

        $this->assertSame('Salam netral', (new AiCommsService($factory))->generateMessage('Uji netral'));
    }
}
