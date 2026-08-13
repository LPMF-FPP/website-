<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_notifications_and_security_can_be_updated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $payload = [
            'notifications' => [
                'email' => [
                    'enabled' => true,
                    'default_recipient' => 'lab@test.dev',
                    'subject' => 'Tes',
                    'body' => 'Message',
                ],
                'whatsapp' => [
                    'enabled' => false,
                    'default_target' => '',
                    'message' => '',
                ],
            ],
            'security' => [
                'roles' => [
                    'can_manage_settings' => ['admin'],
                    'can_issue_number' => ['admin', 'analyst'],
                ],
            ],
        ];

        $response = $this->actingAs($user)->putJson('/api/settings/notifications-security', $payload);

        $response->assertOk()
            ->assertJsonPath('notifications.email.default_recipient', 'lab@test.dev');
    }

    public function test_notification_test_endpoint_sends_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson('/api/settings/notifications/test', [
            'channel' => 'email',
            'target' => 'tester@example.com',
            'message' => 'Hello',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'delivered');
    }

    public function test_notification_test_endpoint_queues_whatsapp_in_the_central_log(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson('/api/settings/notifications/test', [
            'channel' => 'whatsapp',
            'target' => '08123456789',
            'message' => 'Tes log WhatsApp',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('message', 'Pesan uji WhatsApp telah diantrikan.');
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'source_label' => 'Pesan uji pengaturan',
            'status' => 'pending',
            'recipient_jid' => '628123456789@s.whatsapp.net',
        ]);
    }
}
