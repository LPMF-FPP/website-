<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AiCommsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use RuntimeException;

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
                            'content' => 'Hello WhatsApp'
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = new AiCommsService();
        $result = $service->generateMessage('Draft a message');

        $this->assertEquals('Hello WhatsApp', $result);
    }

    public function test_generate_message_api_error()
    {
        Http::fake([
            'https://api.openai.com/v1/*' => Http::response('Error', 500),
        ]);

        $this->expectException(RuntimeException::class);

        $service = new AiCommsService();
        $service->generateMessage('Draft a message');
    }

    public function test_missing_api_key()
    {
        Config::set('services.ai.key', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI service is not configured.');

        $service = new AiCommsService();
        $service->generateMessage('Draft a message');
    }
}
