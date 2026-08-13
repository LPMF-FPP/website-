<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendPersistedWhatsAppMessage;
use App\Models\User;
use App\Models\WhatsAppMessageBatch;
use App\Models\WhatsAppMessageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppHubOutboundRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('manage-settings', fn (User $user): bool => $user->role === 'admin');
    }

    public function test_log_and_retry_require_manage_settings(): void
    {
        $message = $this->retryableMessage();
        $user = User::factory()->create(['role' => 'analis']);

        $this->actingAs($user)->getJson(route('whatsapp.logs'))->assertForbidden();
        $this->actingAs($user)
            ->postJson(route('whatsapp.logs.messages.retry', $message))
            ->assertForbidden();
    }

    public function test_retry_endpoint_requeues_only_stored_failed_payload_and_audits_the_request(): void
    {
        Queue::fake();
        $message = $this->retryableMessage();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->postJson(route('whatsapp.logs.messages.retry', $message));

        $response->assertStatus(202)
            ->assertJsonPath('message_log.id', $message->id);
        Queue::assertPushed(SendPersistedWhatsAppMessage::class, 1);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'id' => $message->id,
            'status' => WhatsAppMessageLog::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $user->id,
            'action' => 'WHATSAPP_OUTBOUND_RETRY_REQUESTED',
            'subject_type' => WhatsAppMessageLog::class,
            'subject_id' => $message->id,
        ]);
    }

    public function test_retry_endpoint_rejects_browser_supplied_payload_fields(): void
    {
        Queue::fake();
        $message = $this->retryableMessage();
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->postJson(route('whatsapp.logs.messages.retry', $message), [
                'message' => 'Payload baru',
                'phone' => '628999999999',
                'attachment' => 'new-file',
            ])
            ->assertUnprocessable();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'id' => $message->id,
            'status' => WhatsAppMessageLog::STATUS_FAILED,
        ]);
    }

    public function test_sent_and_unknown_messages_cannot_be_retried(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        foreach ([WhatsAppMessageLog::STATUS_SENT, WhatsAppMessageLog::STATUS_UNKNOWN] as $status) {
            $message = $this->retryableMessage(['status' => $status, 'retryable' => false]);
            $this->actingAs($user)
                ->postJson(route('whatsapp.logs.messages.retry', $message))
                ->assertConflict();
        }
    }

    public function test_log_endpoint_returns_requested_page_of_messages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 26) as $number) {
            WhatsAppMessageLog::query()->create([
                'recipient_jid' => "628123456{$number}@s.whatsapp.net",
                'recipient_name' => "Penerima {$number}",
                'recipient_type' => 'individual',
                'status' => WhatsAppMessageLog::STATUS_FAILED,
            ]);
        }

        $this->actingAs($user)
            ->getJson(route('whatsapp.logs', ['logs_page' => 2]))
            ->assertOk()
            ->assertJsonPath('messages.current_page', 2)
            ->assertJsonPath('messages.last_page', 2)
            ->assertJsonCount(1, 'messages.data');
    }

    public function test_log_endpoint_returns_a_safe_preview_from_the_stored_payload(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $message = $this->retryableMessage();

        $this->actingAs($user)
            ->getJson(route('whatsapp.logs'))
            ->assertOk()
            ->assertJsonPath('messages.data.0.id', $message->id)
            ->assertJsonPath('messages.data.0.message_preview', 'Payload tersimpan')
            ->assertJsonPath('messages.data.0.message_preview_source', 'stored_payload')
            ->assertJsonMissing(['payload_encrypted' => $message->payload_encrypted]);
    }

    public function test_legacy_failed_log_uses_its_batch_preview_and_explains_retry_is_unavailable(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $batch = WhatsAppMessageBatch::query()->create([
            'type' => 'reminder',
            'title' => 'Pengingat historis',
            'message_preview' => 'Pesan pengingat historis',
            'total_recipients' => 1,
        ]);
        $message = WhatsAppMessageLog::query()->create([
            'batch_id' => $batch->id,
            'recipient_jid' => '628123456789@g.us',
            'recipient_name' => 'Grup Pengingat',
            'recipient_type' => 'group',
            'status' => WhatsAppMessageLog::STATUS_FAILED,
        ]);

        $this->actingAs($user)
            ->getJson(route('whatsapp.logs'))
            ->assertOk()
            ->assertJsonPath('messages.data.0.id', $message->id)
            ->assertJsonPath('messages.data.0.message_preview', 'Pesan pengingat historis')
            ->assertJsonPath('messages.data.0.message_preview_source', 'historical_batch')
            ->assertJsonPath('messages.data.0.is_legacy_log', true)
            ->assertJsonPath('messages.data.0.retry_available', false)
            ->assertJsonPath('messages.data.0.retry_block_reason', 'Log ini dibuat sebelum fitur retry aman aktif. Payload asli tidak tersedia; kirim ulang dari sumber pesan untuk membuat log baru yang dapat diulang bila gagal.');
    }

    public function test_imported_outbox_log_displays_its_safe_preview_and_cannot_be_retried(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $message = WhatsAppMessageLog::query()->create([
            'recipient_jid' => '628123456789@s.whatsapp.net',
            'recipient_name' => 'Penyidik',
            'recipient_type' => 'individual',
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'transport' => WhatsAppMessageLog::TRANSPORT_LEGACY_OUTBOX,
            'payload_encrypted' => Crypt::encryptString(json_encode([
                'kind' => 'text',
                'recipient_jid' => '628123456789@s.whatsapp.net',
                'message' => 'Dokumen siap untuk diambil.',
                'mentions' => [],
            ], JSON_THROW_ON_ERROR)),
            'source_label' => 'Notifikasi siap diambil',
            'retryable' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('whatsapp.logs'))
            ->assertOk()
            ->assertJsonPath('messages.data.0.id', $message->id)
            ->assertJsonPath('messages.data.0.message_preview', 'Dokumen siap untuk diambil.')
            ->assertJsonPath('messages.data.0.message_preview_source', 'historical_outbox')
            ->assertJsonPath('messages.data.0.is_legacy_log', true)
            ->assertJsonPath('messages.data.0.retry_available', false)
            ->assertJsonPath('messages.data.0.retry_block_reason', 'Log ini diimpor dari outbox sebelum fitur retry aman aktif. Pengiriman ulang diblokir untuk mencegah pesan ganda.');
    }

    public function test_qmh_action_codes_are_redacted_from_message_previews(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $message = $this->retryableMessage([
            'payload_encrypted' => Crypt::encryptString(json_encode([
                'kind' => 'text',
                'recipient_jid' => '628123456789@s.whatsapp.net',
                'message' => '/qmh 42 approve SECRET-CODE',
                'mentions' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = $this->actingAs($user)->getJson(route('whatsapp.logs'))->assertOk();

        $this->assertSame('/qmh 42 approve [REDACTED]', $response->json('messages.data.0.message_preview'));
        $this->assertStringNotContainsString('SECRET-CODE', json_encode($response->json(), JSON_THROW_ON_ERROR));
    }

    public function test_file_without_a_caption_uses_a_generic_preview(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $message = $this->retryableMessage([
            'payload_encrypted' => Crypt::encryptString(json_encode([
                'kind' => 'file',
                'recipient_jid' => '628123456789@s.whatsapp.net',
                'caption' => '',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->actingAs($user)
            ->getJson(route('whatsapp.logs'))
            ->assertOk()
            ->assertJsonPath('messages.data.0.id', $message->id)
            ->assertJsonPath('messages.data.0.message_preview', 'Lampiran dikirim tanpa pesan tambahan.');
    }

    public function test_credentials_are_redacted_from_message_previews(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $message = $this->retryableMessage([
            'payload_encrypted' => Crypt::encryptString(json_encode([
                'kind' => 'text',
                'recipient_jid' => '628123456789@s.whatsapp.net',
                'message' => 'api_key=super-secret-value',
                'mentions' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = $this->actingAs($user)->getJson(route('whatsapp.logs'))->assertOk();

        $this->assertSame('api_key=[REDACTED]', $response->json('messages.data.0.message_preview'));
        $this->assertStringNotContainsString('super-secret-value', json_encode($response->json(), JSON_THROW_ON_ERROR));
    }

    private function retryableMessage(array $overrides = []): WhatsAppMessageLog
    {
        return WhatsAppMessageLog::query()->create(array_merge([
            'recipient_jid' => '628123456789@s.whatsapp.net',
            'recipient_name' => 'Penerima',
            'recipient_type' => 'individual',
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'transport' => 'gowa',
            'payload_encrypted' => Crypt::encryptString(json_encode([
                'kind' => 'text',
                'recipient_jid' => '628123456789@s.whatsapp.net',
                'message' => 'Payload tersimpan',
                'mentions' => [],
            ], JSON_THROW_ON_ERROR)),
            'retryable' => true,
            'attempt_count' => 1,
        ], $overrides));
    }
}
