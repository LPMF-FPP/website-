<?php

namespace Tests\Feature\Api;

use App\Models\TestRequest;
use App\Models\WhatsappCommandLog;
use App\Models\WhatsappWhitelist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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

    public function test_help_command_returns_updated_menu_copy(): void
    {
        Http::fake([
            '*' => Http::response([
                'results' => [
                    'message_id' => 'reply-help-123',
                ],
            ], 200),
        ]);

        $data = [
            'from' => '123456789',
            'body' => '/help',
            'message_id' => 'incoming-help-001',
        ];
        $payload = json_encode($data);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_key');

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );

        $response->assertStatus(200);

        $log = WhatsappCommandLog::query()->firstOrFail();

        $this->assertSame('/help', $log->command);
        $this->assertSame('success', $log->response_status);
        $this->assertStringContainsString('Berikut perintah yang tersedia', (string) $log->response_text);
        $this->assertStringContainsString('/status', (string) $log->response_text);
        $this->assertStringNotContainsString('fitur otomatis', strtolower((string) $log->response_text));
    }

    public function test_status_command_requires_whitelist_from_webhook(): void
    {
        Http::fake([
            '*' => Http::response([
                'results' => [
                    'message_id' => 'reply-status-denied-123',
                ],
            ], 200),
        ]);

        $data = [
            'from' => '123456789',
            'body' => '/status',
            'message_id' => 'incoming-status-denied-001',
        ];
        $payload = json_encode($data);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_key');

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );

        $response->assertStatus(200);

        $log = WhatsappCommandLog::query()->firstOrFail();

        $this->assertSame('/status', $log->command);
        $this->assertSame('invalid', $log->response_status);
        $this->assertStringContainsString('tidak memiliki izin', strtolower((string) $log->response_text));
    }

    public function test_status_command_returns_updated_summary_for_whitelisted_user(): void
    {
        Http::fake([
            '*' => Http::response([
                'results' => [
                    'message_id' => 'reply-status-123',
                ],
            ], 200),
        ]);

        WhatsappWhitelist::query()->create([
            'phone_number' => '62123456789',
            'name' => 'Operator Test',
        ]);

        $submittedRequest = TestRequest::factory()->create(['status' => 'submitted']);
        $testingRequest = TestRequest::factory()->create(['status' => 'in_testing']);
        $readyRequest = TestRequest::factory()->create(['status' => 'ready_for_delivery']);
        $completedRequest = TestRequest::factory()->create(['status' => 'completed']);

        $submittedRequest->samples()->create([
            'short_description' => 'Sampel A',
            'sample_description' => 'Sampel A untuk pengujian awal',
            'sample_form' => 'powder',
            'sample_category' => 'other',
            'quantity' => 1,
            'unit' => 'gram',
            'condition' => 'baik',
            'sample_status' => 'received',
        ]);

        $testingRequest->samples()->create([
            'short_description' => 'Sampel B',
            'sample_description' => 'Sampel B untuk proses pengujian',
            'sample_form' => 'liquid',
            'sample_category' => 'other',
            'quantity' => 1,
            'unit' => 'ml',
            'condition' => 'baik',
            'sample_status' => 'in_testing',
        ]);

        $readyRequest->samples()->create([
            'short_description' => 'Sampel C',
            'sample_description' => 'Sampel C siap diserahkan',
            'sample_form' => 'pill',
            'sample_category' => 'other',
            'quantity' => 1,
            'unit' => 'tablet',
            'condition' => 'baik',
            'sample_status' => 'tested',
        ]);

        $completedRequest->samples()->create([
            'short_description' => 'Sampel D',
            'sample_description' => 'Sampel D selesai diproses',
            'sample_form' => 'capsule',
            'sample_category' => 'other',
            'quantity' => 1,
            'unit' => 'kapsul',
            'condition' => 'baik',
            'sample_status' => 'tested',
        ]);

        $data = [
            'from' => '123456789',
            'body' => '/status',
            'message_id' => 'incoming-status-001',
        ];
        $payload = json_encode($data);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_key');

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature],
            $payload
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);

        $log = WhatsappCommandLog::query()->firstOrFail();

        $this->assertSame('/status', $log->command);
        $this->assertSame('success', $log->response_status);
        $this->assertNotNull($log->processed_at);
        $this->assertStringContainsString('Ringkasan Layanan Laboratorium', (string) $log->response_text);
        $this->assertStringContainsString('*Total permintaan:* 4', (string) $log->response_text);
        $this->assertStringContainsString('*Total sampel:* 4', (string) $log->response_text);
        $this->assertStringContainsString('*Permintaan aktif:* 3', (string) $log->response_text);
        $this->assertStringContainsString('Siap diserahkan: 1', (string) $log->response_text);
        $this->assertStringContainsString('*Sampel aktif:* 3', (string) $log->response_text);
        $this->assertStringContainsString('*Selesai / serah terima:* 1', (string) $log->response_text);
        $this->assertStringNotContainsString('Total Ongoing', (string) $log->response_text);
    }
}
