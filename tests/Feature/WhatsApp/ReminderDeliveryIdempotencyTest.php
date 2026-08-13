<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendReminderJob;
use App\Models\Reminder;
use App\Models\ReminderRecipient;
use App\Models\WhatsAppMessageLog;
use App\Services\Reminders\Handlers\CountdownHandler;
use App\Services\Reminders\Handlers\TemperatureReminderHandler;
use App\Services\Reminders\ReminderService;
use App\Services\WhatsApp\GowaClient;
use App\Services\WhatsApp\OutboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class ReminderDeliveryIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_scheduled_runs_share_one_daily_delivery_key(): void
    {
        Queue::fake();
        $reminder = $this->reminder();

        Artisan::call('reminders:send');
        Artisan::call('reminders:send');

        Queue::assertPushed(SendReminderJob::class, function (SendReminderJob $job) use ($reminder): bool {
            return $job->reminderId === $reminder->id
                && $job->deliveryKey === 'scheduled:'.now()->toDateString();
        });
        Queue::assertPushed(SendReminderJob::class, 1);
    }

    public function test_same_delivery_key_reuses_one_envelope_instead_of_sending_twice(): void
    {
        $reminder = $this->reminder();
        $recipient = ReminderRecipient::query()->where('reminder_id', $reminder->id)->firstOrFail();

        $this->mock(GowaClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturn([
                    'success' => true,
                    'outcome' => 'sent',
                    'status' => 200,
                    'message_id' => 'message-id',
                ]);
        });

        $service = new ReminderService(
            app(OutboundMessageService::class),
            app(CountdownHandler::class),
            app(TemperatureReminderHandler::class),
        );

        $deliveryKey = 'scheduled:'.now()->toDateString();
        $service->process($reminder, $deliveryKey);
        $service->process($reminder->fresh(), $deliveryKey);

        $this->assertDatabaseCount('whatsapp_message_logs', 1);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_type' => Reminder::class,
            'source_id' => $reminder->id,
            'idempotency_key' => 'whatsapp-reminder:'.$reminder->id.':'.$deliveryKey.':recipient:'.$recipient->id,
            'status' => WhatsAppMessageLog::STATUS_SENT,
            'attempt_count' => 1,
        ]);
    }

    public function test_legacy_serialized_job_can_still_process_its_reminder(): void
    {
        $reminder = $this->reminder();
        $job = new SendReminderJob($reminder->id);
        $job->reminderId = null;
        $job->reminder = $reminder;
        $job->deliveryKey = '';

        $service = $this->mock(ReminderService::class, function (MockInterface $mock) use ($reminder): void {
            $mock->shouldReceive('process')
                ->once()
                ->withArgs(function (Reminder $actualReminder, string $deliveryKey) use ($reminder): bool {
                    return $actualReminder->is($reminder)
                        && $deliveryKey === 'legacy:'.$reminder->id.':'.now()->toDateString();
                });
        });

        $job->handle($service);
    }

    private function reminder(): Reminder
    {
        $reminder = Reminder::query()->create([
            'type' => 'custom',
            'name' => 'Pengingat Pengujian',
            'is_enabled' => true,
            'schedule_time' => now()->subMinute()->format('H:i:s'),
            'schedule_days' => [now()->format('D')],
            'message_template' => 'Pesan pengingat',
        ]);

        ReminderRecipient::query()->create([
            'reminder_id' => $reminder->id,
            'recipient_type' => 'individual',
            'recipient_value' => '628123456789',
        ]);

        return $reminder;
    }
}
