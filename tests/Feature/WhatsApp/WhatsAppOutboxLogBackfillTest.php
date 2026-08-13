<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class WhatsAppOutboxLogBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_legacy_outbox_messages_once_without_making_them_retryable(): void
    {
        $sent = WhatsappOutbox::query()->create([
            'milestone_key' => 'REQUEST_RECEIVED',
            'to_phone_e164' => '628123456789',
            'to_jid' => '628123456789@s.whatsapp.net',
            'message_text' => 'Berita Acara Penerimaan telah dibuat.',
            'provider_message_id' => 'provider-sent',
            'status' => 'sent',
            'attempts' => 1,
        ]);
        $failed = WhatsappOutbox::query()->create([
            'milestone_key' => 'READY_FOR_PICKUP',
            'to_phone_e164' => '628987654321',
            'to_jid' => '628987654321@s.whatsapp.net',
            'message_text' => 'Dokumen siap diambil.',
            'status' => 'failed',
            'attempts' => 2,
        ]);
        $queued = WhatsappOutbox::query()->create([
            'milestone_key' => 'HANDOVER_COMPLETED',
            'to_phone_e164' => '628111111111',
            'to_jid' => '628111111111@s.whatsapp.net',
            'message_text' => 'Penyerahan selesai.',
            'status' => 'queued',
            'attempts' => 0,
        ]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertDatabaseCount('whatsapp_message_logs', 3);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_type' => WhatsappOutbox::class,
            'source_id' => $sent->id,
            'source_label' => 'Notifikasi Berita Acara Penerimaan',
            'status' => WhatsAppMessageLog::STATUS_SENT,
            'transport' => WhatsAppMessageLog::TRANSPORT_LEGACY_OUTBOX,
            'retryable' => false,
            'message_id' => 'provider-sent',
        ]);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_type' => WhatsappOutbox::class,
            'source_id' => $failed->id,
            'source_label' => 'Notifikasi siap diambil',
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'transport' => WhatsAppMessageLog::TRANSPORT_LEGACY_OUTBOX,
            'retryable' => false,
            'attempt_count' => 2,
        ]);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_type' => WhatsappOutbox::class,
            'source_id' => $queued->id,
            'source_label' => 'Notifikasi Berita Acara Penyerahan',
            'status' => WhatsAppMessageLog::STATUS_UNKNOWN,
            'transport' => WhatsAppMessageLog::TRANSPORT_LEGACY_OUTBOX,
            'retryable' => false,
            'attempt_count' => 0,
        ]);

        $log = WhatsAppMessageLog::query()->where('source_id', $failed->id)->firstOrFail();
        $payload = json_decode(Crypt::decryptString((string) $log->payload_encrypted), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Dokumen siap diambil.', $payload['message']);
        $this->assertSame('Log ini diimpor dari outbox sebelum fitur retry aman aktif. Pengiriman ulang diblokir untuk mencegah pesan ganda.', $log->retry_block_reason);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_13_000001_backfill_whatsapp_outbox_logs.php');
    }
}
