<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendPersistedWhatsAppMessage;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\OutboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class OutboundMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_attempts_and_only_marks_confirmed_provider_failure_retryable(): void
    {
        $this->mock(GowaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturn([
                    'success' => false,
                    'outcome' => 'failed',
                    'status' => 503,
                    'error' => 'Provider WhatsApp menolak pengiriman (HTTP 503).',
                ]);
        });

        $result = app(OutboundMessageService::class)->sendText('628123456789@s.whatsapp.net', 'Pesan uji');

        $this->assertFalse($result['success']);
        $this->assertSame(WhatsAppMessageLog::STATUS_FAILED, $result['state']);
        $this->assertTrue($result['retryable']);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'id' => $result['message_log_id'],
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'retryable' => true,
            'attempt_count' => 1,
        ]);
        $this->assertDatabaseHas('whatsapp_message_attempts', [
            'whatsapp_message_log_id' => $result['message_log_id'],
            'attempt_number' => 1,
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'provider_status' => 503,
        ]);
    }

    public function test_it_marks_connection_loss_unknown_and_never_retryable(): void
    {
        $this->mock(GowaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturn([
                    'success' => false,
                    'outcome' => 'unknown',
                    'error' => 'Koneksi ke provider WhatsApp terputus; status pengiriman tidak dapat dipastikan.',
                ]);
        });

        $result = app(OutboundMessageService::class)->sendText('628123456789@s.whatsapp.net', 'Pesan uji');

        $this->assertSame(WhatsAppMessageLog::STATUS_UNKNOWN, $result['state']);
        $this->assertFalse($result['retryable']);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'id' => $result['message_log_id'],
            'status' => WhatsAppMessageLog::STATUS_UNKNOWN,
            'retryable' => false,
        ]);
    }

    public function test_it_records_a_confirmed_send_as_terminal_and_not_retryable(): void
    {
        $this->mock(GowaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturn([
                    'success' => true,
                    'outcome' => 'sent',
                    'status' => 200,
                    'message_id' => 'provider-message-id',
                ]);
        });

        $result = app(OutboundMessageService::class)->sendText('628123456789@s.whatsapp.net', 'Pesan uji');

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'id' => $result['message_log_id'],
            'status' => WhatsAppMessageLog::STATUS_SENT,
            'retryable' => false,
            'message_id' => 'provider-message-id',
        ]);
        $this->assertDatabaseHas('whatsapp_message_attempts', [
            'whatsapp_message_log_id' => $result['message_log_id'],
            'status' => WhatsAppMessageLog::STATUS_SENT,
        ]);
    }

    public function test_retry_conditionally_claims_one_failed_envelope_and_reuses_the_stored_payload(): void
    {
        Queue::fake();
        $service = app(OutboundMessageService::class);
        $log = WhatsAppMessageLog::query()->create([
            'recipient_jid' => '628123456789@s.whatsapp.net',
            'recipient_name' => 'Penerima',
            'recipient_type' => 'individual',
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'transport' => 'gowa',
            'payload_encrypted' => Crypt::encryptString(json_encode([
                'kind' => 'text',
                'recipient_jid' => '628123456789@s.whatsapp.net',
                'message' => 'Payload asli',
                'mentions' => [],
            ], JSON_THROW_ON_ERROR)),
            'retryable' => true,
            'attempt_count' => 1,
        ]);

        $this->assertTrue($service->retry($log));
        $this->assertFalse($service->retry($log->fresh()));

        Queue::assertPushed(SendPersistedWhatsAppMessage::class, function (SendPersistedWhatsAppMessage $job) use ($log): bool {
            return $job->messageLogId === $log->id;
        });
        Queue::assertPushed(SendPersistedWhatsAppMessage::class, 1);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'id' => $log->id,
            'status' => WhatsAppMessageLog::STATUS_PENDING,
            'retryable' => false,
        ]);
    }

    public function test_retry_uses_private_attachment_snapshot_after_the_source_file_is_removed(): void
    {
        Storage::fake('local');
        Queue::fake();
        $source = tempnam(sys_get_temp_dir(), 'outbound-wa-');
        file_put_contents($source, 'attachment body');

        $this->mock(GowaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendFile')
                ->once()
                ->andReturn([
                    'success' => false,
                    'outcome' => 'failed',
                    'status' => 400,
                    'error' => 'Provider WhatsApp menolak pengiriman (HTTP 400).',
                ]);
        });

        try {
            $result = app(OutboundMessageService::class)->sendFile(
                '628123456789@s.whatsapp.net',
                $source,
                'Lampiran',
                'hasil.pdf'
            );
        } finally {
            @unlink($source);
        }

        $log = WhatsAppMessageLog::query()->findOrFail($result['message_log_id']);
        Storage::disk('local')->assertExists((string) $log->attachment_path);

        $this->assertTrue(app(OutboundMessageService::class)->retry($log));
        $this->assertSame(WhatsAppMessageLog::STATUS_PENDING, $log->fresh()->status);
        Queue::assertPushed(SendPersistedWhatsAppMessage::class, 1);
    }

    public function test_file_envelope_is_not_sendable_until_its_private_snapshot_is_available(): void
    {
        Queue::fake();

        $log = app(OutboundMessageService::class)->queueFile(
            '628123456789@s.whatsapp.net',
            '/missing/source.pdf',
            'Lampiran',
            'source.pdf'
        );

        $this->assertSame(WhatsAppMessageLog::STATUS_BLOCKED, $log->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_invalid_file_is_blocked_without_creating_a_private_snapshot(): void
    {
        Storage::fake('local');
        Queue::fake();
        $source = tempnam(sys_get_temp_dir(), 'outbound-wa-invalid-');
        file_put_contents($source, '<html>not an allowed attachment</html>');

        try {
            $log = app(OutboundMessageService::class)->queueFile(
                '628123456789@s.whatsapp.net',
                $source,
                'Lampiran',
                'source.html'
            );
        } finally {
            @unlink($source);
        }

        $this->assertSame(WhatsAppMessageLog::STATUS_BLOCKED, $log->fresh()->status);
        $this->assertNull($log->fresh()->attachment_path);
        Queue::assertNothingPushed();
        Storage::disk('local')->assertDirectoryEmpty('whatsapp-outbound');
    }
}
