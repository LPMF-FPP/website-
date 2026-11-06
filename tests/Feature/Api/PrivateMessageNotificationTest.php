<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\WhatsappCommandLog;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsApp\OutboundMessageService;
use App\Services\WhatsApp\PrivateMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrivateMessageNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.whatsapp.webhook_secret', 'test-secret');
        Config::set('services.whatsapp.incoming_group', '120363394554813102');
        Queue::fake();
    }

    public function test_private_message_is_queued_once_without_keyword(): void
    {
        $data = $this->message();
        $this->sendWebhook($data)->assertOk();
        $this->sendWebhook($data)->assertOk()->assertJsonPath('dedupe', 'provider_message_id');

        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();
        $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true);
        $this->assertSame('120363394554813102@g.us', $payload['recipient_jid']);
        $this->assertStringContainsString('Selamat pagi', $payload['message']);
        $this->assertStringContainsString('https://wa.me/6281234567890', $payload['message']);
    }

    public function test_notification_delivery_uses_group_and_does_not_send_twice(): void
    {
        Http::fake(['*' => Http::response(['results' => ['message_id' => 'sent-id']], 200)]);
        $this->sendWebhook($this->message())->assertOk();
        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();

        $sender = app(OutboundMessageService::class);
        $sender->deliver($log->id);
        $sender->deliver($log->id);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['phone'] === '120363394554813102@g.us'
            && str_contains($request['message'], 'Selamat pagi'));
        $this->assertSame(WhatsAppMessageLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_group_self_and_unresolved_lid_are_ignored(): void
    {
        foreach ([
            ['chat_id' => '120363394554813102@g.us'],
            ['is_from_me' => true],
            ['from' => '12345678901234@lid'],
            ['chat_id' => '6289876543210@s.whatsapp.net'],
        ] as $change) {
            $data = $this->message();
            $data['payload'] = array_replace($data['payload'], $change);
            $this->sendWebhook($data)->assertOk()->assertJsonPath('status', 'ignored');
        }
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
    }

    public function test_attachment_without_caption_is_not_downloaded_or_forwarded(): void
    {
        $data = $this->message();
        $data['payload']['body'] = '';
        $data['payload']['document'] = ['url' => 'https://example.invalid/document'];
        $this->sendWebhook($data)->assertOk();

        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();
        $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true);
        $this->assertStringContainsString('Lampiran: Dokumen', $payload['message']);
        $this->assertStringNotContainsString('https://example.invalid', $payload['message']);
        $this->assertSame('text', $payload['kind']);
    }

    public function test_qmh_action_code_is_not_forwarded(): void
    {
        $data = $this->message();
        $data['payload']['body'] = '/qmh 42 approve SECRET-CODE-123 alasan';
        $this->sendWebhook($data)->assertOk();

        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();
        $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true);
        $this->assertStringNotContainsString('SECRET-CODE-123', $payload['message']);
        $this->assertStringContainsString('[REDACTED]', $payload['message']);
    }

    public function test_qmh_code_with_tab_and_surrounding_text_is_redacted(): void
    {
        $data = $this->message();
        $data['payload']['body'] = "Pesan: /qmh\t42 approve SECRET-CODE-123 alasan";
        $this->sendWebhook($data)->assertOk();

        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();
        $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true);
        $this->assertStringNotContainsString('SECRET-CODE-123', $payload['message']);
        $this->assertStringContainsString('[REDACTED]', $payload['message']);
    }

    public function test_caption_is_included_and_its_qmh_code_is_redacted(): void
    {
        $data = $this->message();
        $data['payload']['body'] = '';
        $data['payload']['caption'] = '/qmh 42 approve SECRET-CODE-123 alasan';
        $data['payload']['image'] = ['url' => 'https://example.invalid/image'];
        $this->sendWebhook($data)->assertOk();

        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();
        $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true);
        $this->assertStringContainsString('Lampiran: Foto', $payload['message']);
        $this->assertStringContainsString('[REDACTED]', $payload['message']);
        $this->assertStringNotContainsString('SECRET-CODE-123', $payload['message']);
        $this->assertStringNotContainsString('example.invalid', json_encode(WhatsappCommandLog::query()->sole()->params));
    }

    public function test_notification_is_disabled_without_group_configuration(): void
    {
        Config::set('services.whatsapp.incoming_group', null);
        $this->sendWebhook($this->message())->assertOk();
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
        $this->assertDatabaseCount('whatsapp_command_logs', 1);
    }

    public function test_notification_failure_rolls_back_the_inbound_log(): void
    {
        Log::spy();
        $this->mock(PrivateMessageNotification::class)
            ->shouldReceive('queue')->once()->andThrow(new \RuntimeException('private content 6281234567890'));

        $this->sendWebhook($this->message())->assertStatus(500);
        $this->assertDatabaseCount('whatsapp_command_logs', 0);
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
        Log::shouldHaveReceived('error')->with('Webhook dispatch error', ['exception' => \RuntimeException::class])->once();
    }

    public function test_message_without_provider_id_is_notified_once_for_a_replayed_event(): void
    {
        $data = $this->message();
        unset($data['payload']['id']);
        $data['payload']['timestamp'] = '2026-11-06T13:15:45Z';
        $this->sendWebhook($data)->assertOk();
        $this->sendWebhook($data)->assertOk()->assertJsonPath('dedupe', 'message_fingerprint');

        $this->assertDatabaseCount('whatsapp_command_logs', 1);
        $this->assertDatabaseCount('whatsapp_message_logs', 1);
    }

    public function test_idless_messages_with_distinct_timestamps_are_not_deduplicated(): void
    {
        $first = $this->message();
        unset($first['payload']['id']);
        $first['payload']['timestamp'] = '2026-11-06T13:15:45Z';
        $second = $first;
        $second['payload']['timestamp'] = '2026-11-06T13:15:46Z';

        $this->sendWebhook($first)->assertOk();
        $this->sendWebhook($second)->assertOk();
        $this->assertDatabaseCount('whatsapp_message_logs', 2);
    }

    public function test_lid_only_sender_is_notified_without_fabricating_a_phone_or_running_commands(): void
    {
        $data = $this->message();
        $data['payload']['from'] = '12345678901234@lid';
        $data['payload']['chat_id'] = '12345678901234@lid';
        $data['payload']['body'] = '/qmh 42 approve SECRET-CODE-123 alasan';
        $this->sendWebhook($data)->assertOk();
        $this->sendWebhook($data)->assertOk();

        $this->assertDatabaseCount('whatsapp_command_logs', 0);
        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();
        $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true);
        $this->assertStringContainsString('Nomor:* Tidak tersedia', $payload['message']);
        $this->assertStringNotContainsString('12345678901234', $payload['message']);
        $this->assertStringNotContainsString('wa.me', $payload['message']);
        $this->assertStringNotContainsString('SECRET-CODE-123', $payload['message']);
    }

    public function test_provider_chat_lid_mapping_can_supply_a_verified_phone(): void
    {
        $data = $this->message();
        $data['payload']['from'] = '12345678901234@lid';
        $data['payload']['chat_lid'] = '12345678901234@lid';
        $this->sendWebhook($data)->assertOk();

        $log = WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->sole();
        $payload = json_decode(Crypt::decryptString($log->payload_encrypted), true);
        $this->assertStringContainsString('https://wa.me/6281234567890', $payload['message']);
        $this->assertDatabaseCount('whatsapp_command_logs', 1);
    }

    public function test_identical_private_messages_with_distinct_ids_each_queue_a_notification(): void
    {
        $first = $this->message();
        $second = $first;
        $second['payload']['id'] = 'another-message';
        $this->sendWebhook($first)->assertOk();
        $this->sendWebhook($second)->assertOk();

        $this->assertSame(2, WhatsAppMessageLog::query()->where('source_type', 'incoming_private_message')->count());
    }

    public function test_ignored_message_reason_is_logged_without_payload(): void
    {
        Log::spy();
        $data = $this->message();
        $data['payload']['from'] = '12345678901234@lid';
        $this->sendWebhook($data)->assertOk()->assertJsonPath('reason', 'unsupported_event');

        Log::shouldHaveReceived('info')->with('WhatsApp webhook message ignored', [
            'reason' => 'unresolved_sender',
        ])->once();
    }

    private function message(): array
    {
        return ['event' => 'message', 'device_id' => 'device', 'payload' => [
            'id' => 'test-message',
            'from' => '6281234567890@s.whatsapp.net',
            'chat_id' => '6281234567890@s.whatsapp.net',
            'is_from_me' => false,
            'body' => 'Selamat pagi',
        ]];
    }

    private function sendWebhook(array $data): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($data);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

        return $this->call('POST', '/api/whatsapp/webhook', [], [], [],
            ['HTTP_X-Hub-Signature-256' => $signature], $body);
    }
}
