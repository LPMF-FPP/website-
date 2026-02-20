<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AiCommsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiCommsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.ai.base_url', 'https://api.openai.com/v1');
        Config::set('services.ai.key', 'test-key');
        Config::set('services.ai.model', 'gpt-3.5-turbo');
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
                    ],
                ],
            ], 200),
        ]);

        $service = new AiCommsService;
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
                    ],
                ],
            ], 200),
        ]);

        $service = new AiCommsService;
        $result = $service->generateMessage('Draft a message');

        $this->assertEquals('Hello WhatsApp', $result);
    }

    public function test_generate_message_api_error()
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response('Error', 500),
        ]);

        $this->expectException(RuntimeException::class);

        $service = new AiCommsService;
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
                $this->assertStringContainsString('{priority}', $systemPrompt);
                $this->assertStringContainsString('{x/y}', $systemPrompt);
                $this->assertStringNotContainsString('{bad*}', $systemPrompt);
                $this->assertStringNotContainsString('{x\\y}', $systemPrompt);
                $this->assertSame(1, substr_count($systemPrompt, '{priority}'));
                $this->assertStringContainsStringIgnoringCase('only', $systemPrompt);
                $this->assertStringContainsStringIgnoringCase('do not invent', $systemPrompt);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Hello WhatsApp',
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = new AiCommsService;
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
        $this->expectExceptionMessage('AI service is not configured.');

        $service = new AiCommsService;
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
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = new AiCommsService;
        $result = $service->generateMessage('Draft a message');

        $this->assertSame('Override OK', $result);
    }
}
