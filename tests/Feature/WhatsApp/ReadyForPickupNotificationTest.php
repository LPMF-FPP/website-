<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendPersistedWhatsAppMessage;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use App\Models\WhatsAppMessageLog;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReadyForPickupNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();

        // Enable WhatsApp notifications
        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.enabled'],
            ['value' => true]
        );
        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.enabled_milestones'],
            ['value' => ['READY_FOR_PICKUP']]
        );
        settings_forget_cache();
    }

    public function test_mark_ready_for_delivery_updates_status(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->createOne(['role' => 'admin']);
        $investigator = Investigator::factory()->create([
            'phone' => '08123456789',
        ]);
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'in_testing',
        ]);

        // Add a sample so it's a valid request
        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'status' => 'interpretation_done',
            'package_quantity' => 0,
            'quantity' => 0,
        ]);

        SampleTestProcess::factory()->preparation()->completed()->create([
            'sample_id' => $sample->id,
            'started_at' => now()->subDays(3),
        ]);
        SampleTestProcess::factory()->instrumentation()->completed()->create([
            'sample_id' => $sample->id,
            'started_at' => now()->subDays(2),
        ]);
        SampleTestProcess::factory()->interpretation()->completed()->create([
            'sample_id' => $sample->id,
            'started_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('testing.ready-for-delivery', $testRequest));

        $response->assertRedirect(route('delivery.show', $testRequest));

        $this->assertEquals('ready_for_delivery', $testRequest->fresh()->status);
    }

    public function test_send_pickup_notification_dispatches_job(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->createOne(['role' => 'admin']);
        $investigator = Investigator::factory()->create([
            'phone' => '08123456789',
        ]);

        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);

        $response = $this->actingAs($user)
            ->post(route('delivery.send-notification', $testRequest));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $outbox = \App\Models\WhatsappOutbox::query()
            ->where('test_request_id', $testRequest->id)
            ->where('milestone_key', 'READY_FOR_PICKUP')
            ->firstOrFail();
        $messageLog = \App\Models\WhatsAppMessageLog::query()
            ->where('source_type', \App\Models\WhatsappOutbox::class)
            ->where('source_id', $outbox->id)
            ->firstOrFail();

        $this->assertSame('Notifikasi siap diambil', $messageLog->source_label);
        $this->assertSame('pending', $messageLog->status);
        $this->assertSame('queued', $outbox->fresh()->status);
        Queue::assertPushed(SendPersistedWhatsAppMessage::class, function ($job) use ($messageLog) {
            return $job->messageLogId === $messageLog->id;
        });
    }

    public function test_sending_pickup_notification_again_keeps_the_pending_envelope(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->createOne(['role' => 'admin']);
        $investigator = Investigator::factory()->create(['phone' => '08123456789']);
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);

        $this->actingAs($user)->post(route('delivery.send-notification', $testRequest))->assertSessionHas('success');
        $this->actingAs($user)->post(route('delivery.send-notification', $testRequest))->assertSessionHas('success');

        $outbox = \App\Models\WhatsappOutbox::query()
            ->where('test_request_id', $testRequest->id)
            ->where('milestone_key', 'READY_FOR_PICKUP')
            ->firstOrFail();

        $this->assertDatabaseCount('whatsapp_message_logs', 1);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_type' => \App\Models\WhatsappOutbox::class,
            'source_id' => $outbox->id,
            'status' => 'pending',
        ]);
        Queue::assertPushed(SendPersistedWhatsAppMessage::class, 1);
    }

    public function test_sending_pickup_notification_again_retries_a_confirmed_failed_envelope(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->createOne(['role' => 'admin']);
        $investigator = Investigator::factory()->create(['phone' => '08123456789']);
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);

        $this->actingAs($user)->post(route('delivery.send-notification', $testRequest))->assertSessionHas('success');

        $outbox = \App\Models\WhatsappOutbox::query()
            ->where('test_request_id', $testRequest->id)
            ->where('milestone_key', 'READY_FOR_PICKUP')
            ->firstOrFail();
        $messageLog = WhatsAppMessageLog::query()
            ->where('source_type', \App\Models\WhatsappOutbox::class)
            ->where('source_id', $outbox->id)
            ->firstOrFail();
        $messageLog->update([
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'retryable' => true,
            'attempt_count' => 1,
            'error_message' => 'Provider WhatsApp menolak pengiriman (HTTP 503).',
        ]);

        $this->actingAs($user)->post(route('delivery.send-notification', $testRequest))->assertSessionHas('success');

        $this->assertDatabaseCount('whatsapp_message_logs', 1);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'id' => $messageLog->id,
            'status' => WhatsAppMessageLog::STATUS_PENDING,
            'retryable' => false,
        ]);
        Queue::assertPushed(SendPersistedWhatsAppMessage::class, 2);
    }

    public function test_sending_pickup_notification_again_creates_a_new_envelope_for_a_legacy_failed_log(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::factory()->createOne(['role' => 'admin']);
        $investigator = Investigator::factory()->create(['phone' => '08123456789']);
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'status' => 'ready_for_delivery',
        ]);
        $outbox = \App\Models\WhatsappOutbox::query()->create([
            'test_request_id' => $testRequest->id,
            'milestone_key' => 'READY_FOR_PICKUP',
            'to_phone_e164' => '628123456789',
            'to_jid' => '628123456789@s.whatsapp.net',
            'message_text' => 'Pesan lama.',
            'status' => 'failed',
            'attempts' => 1,
        ]);
        WhatsAppMessageLog::query()->create([
            'recipient_jid' => $outbox->to_jid,
            'recipient_name' => 'Penyidik',
            'recipient_type' => 'individual',
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'transport' => WhatsAppMessageLog::TRANSPORT_LEGACY_OUTBOX,
            'payload_encrypted' => encrypt(json_encode([
                'kind' => 'text',
                'recipient_jid' => $outbox->to_jid,
                'message' => $outbox->message_text,
                'mentions' => [],
            ], JSON_THROW_ON_ERROR)),
            'source_type' => \App\Models\WhatsappOutbox::class,
            'source_id' => $outbox->id,
            'retryable' => false,
        ]);

        $this->actingAs($user)->post(route('delivery.send-notification', $testRequest))->assertSessionHas('success');

        $this->assertDatabaseCount('whatsapp_message_logs', 2);
        $newLog = WhatsAppMessageLog::query()->latest('id')->firstOrFail();
        $this->assertSame(WhatsAppMessageLog::TRANSPORT_GOWA, $newLog->transport);
        $this->assertSame(WhatsAppMessageLog::STATUS_PENDING, $newLog->status);
        $this->assertSame($outbox->id, $newLog->source_id);
        Queue::assertPushed(SendPersistedWhatsAppMessage::class, 1);
    }
}
