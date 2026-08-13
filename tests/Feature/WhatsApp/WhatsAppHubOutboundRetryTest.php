<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendPersistedWhatsAppMessage;
use App\Models\User;
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
