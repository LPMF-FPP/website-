<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
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
        Sample::factory()->create([
            'test_request_id' => $testRequest->id,
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

        Queue::assertPushed(SendWhatsAppNotificationJob::class, function ($job) use ($testRequest) {
            $outbox = \App\Models\WhatsappOutbox::find($job->outboxId);

            return $outbox &&
                $outbox->milestone_key === 'READY_FOR_PICKUP' &&
                $outbox->test_request_id === $testRequest->id;
        });
    }
}
