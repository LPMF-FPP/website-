<?php

namespace Tests\Feature\Api;

use App\Models\WhatsappCommandLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.whatsapp.webhook_secret', 'test_secret_key');
    }

    public function test_webhook_returns_503_when_secret_is_not_configured(): void
    {
        Config::set('services.whatsapp.webhook_secret', null);

        $payload = json_encode(['from' => '123456789', 'body' => 'hello']);
        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [], [], $payload);

        $response->assertStatus(503);
    }

    public function test_webhook_returns_403_when_signature_is_missing()
    {
        $response = $this->postJson('/api/whatsapp/webhook', [
            'event' => 'message',
            'payload' => [],
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_returns_403_when_signature_is_invalid()
    {
        $payload = json_encode(['event' => 'message']);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'wrong_secret');

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );

        $response->assertStatus(403);
    }

    public function test_webhook_returns_200_when_signature_is_valid()
    {
        $payload = json_encode(['from' => '123456789', 'body' => 'test message']);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_key');

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_valid_payload_is_logged()
    {
        $data = ['from' => '123456789', 'body' => 'test logging'];
        $payload = json_encode($data);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_key');

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );

        $response->assertStatus(200);

        $log = WhatsappCommandLog::where('from_jid', '62123456789@s.whatsapp.net')
            ->where('message_text', 'test logging')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('ignored', $log->response_status); // Job processes non-command messages and marks as ignored
        $this->assertJsonStringEqualsJsonString(json_encode($data), json_encode($log->params));
    }

    public function test_webhook_deduplicates_by_provider_message_id(): void
    {
        $data = [
            'message_id' => 'msg-abc-123',
            'from' => '123456789',
            'body' => 'test duplicate',
        ];
        $payload = json_encode($data);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_key');

        $first = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );
        $first->assertStatus(200);

        $second = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );
        $second->assertStatus(200);
        $second->assertJsonPath('dedupe', 'provider_message_id');

        $this->assertDatabaseCount('whatsapp_command_logs', 1);
    }

    public function test_webhook_redacts_qmh_action_code_in_stored_log(): void
    {
        $data = [
            'from' => '123456789',
            'body' => '/qmh 42 approve SECRET-CODE-123 alasan',
        ];

        $payload = json_encode($data);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_key');

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );

        $response->assertStatus(200);

        $log = WhatsappCommandLog::query()->firstOrFail();
        $this->assertStringContainsString('[REDACTED]', $log->message_text);
        $this->assertStringNotContainsString('SECRET-CODE-123', $log->message_text);
    }
}
