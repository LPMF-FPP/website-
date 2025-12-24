<?php

namespace Tests\Feature\Api\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationsSecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_update_notification_settings(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'notifications' => [
                'email' => [
                    'enabled' => true,
                    'default_recipient' => 'admin@example.com',
                    'subject' => 'LIMS Notification',
                    'body' => 'Test body',
                ],
                'whatsapp' => [
                    'enabled' => false,
                    'default_target' => '+6281234567890',
                    'message' => 'Test WhatsApp message',
                ],
            ],
            'security' => [
                'roles' => [
                    'can_manage_settings' => ['admin', 'superadmin'],
                    'can_issue_number' => ['admin', 'staff'],
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/notifications-security', $payload);

        $response->assertOk()
            ->assertJsonPath('notifications.email.enabled', true)
            ->assertJsonPath('notifications.whatsapp.enabled', false)
            ->assertJsonPath('security.can_manage_settings', ['admin', 'superadmin']);

        $setting = SystemSetting::where('key', 'notifications.email.default_recipient')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('admin@example.com', $setting->value);
    }

    public function test_partial_update_notifications_only(): void
    {
        $this->actingAs($this->admin);

        // Update only notifications
        $payload = [
            'notifications' => [
                'email' => [
                    'enabled' => true,
                    'default_recipient' => 'test@example.com',
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/notifications-security', $payload);

        $response->assertOk();
    }

    public function test_partial_update_security_only(): void
    {
        $this->actingAs($this->admin);

        // Update only security
        $payload = [
            'security' => [
                'roles' => [
                    'can_manage_settings' => ['admin'],
                    'can_issue_number' => ['admin', 'staff', 'analyst'],
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/notifications-security', $payload);

        $response->assertOk();
    }

    public function test_can_send_test_email(): void
    {
        Mail::fake();
        $this->actingAs($this->admin);

        $payload = [
            'channel' => 'email',
            'target' => 'test@example.com',
            'message' => 'This is a test email from LIMS',
        ];

        $response = $this->postJson('/api/settings/notifications/test', $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'delivered')
            ->assertJsonStructure(['status', 'message', 'delivered_at']);

        Mail::assertSent(function (\Illuminate\Mail\Mailable $mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_can_send_test_whatsapp(): void
    {
        Log::spy();
        $this->actingAs($this->admin);

        $payload = [
            'channel' => 'whatsapp',
            'target' => '+6281234567890',
            'message' => 'Test WhatsApp notification',
        ];

        $response = $this->postJson('/api/settings/notifications/test', $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'delivered')
            ->assertJsonStructure(['status', 'message']);

        // Verify logged (stub mode)
        Log::shouldHaveReceived('info')
            ->with('WhatsApp message stub', \Mockery::type('array'))
            ->once();
    }

    public function test_test_notification_validates_channel(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'channel' => 'invalid_channel',
            'target' => 'test@example.com',
            'message' => 'Test',
        ];

        $response = $this->postJson('/api/settings/notifications/test', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['channel']);
    }

    public function test_test_email_validates_email_format(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'channel' => 'email',
            'target' => 'not-an-email',
            'message' => 'Test',
        ];

        $response = $this->postJson('/api/settings/notifications/test', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['target']);
    }

    public function test_test_whatsapp_validates_phone_format(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'channel' => 'whatsapp',
            'target' => 'invalid-phone',
            'message' => 'Test',
        ];

        $response = $this->postJson('/api/settings/notifications/test', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['target']);
    }

    public function test_validates_email_in_notification_settings(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'notifications' => [
                'email' => [
                    'default_recipient' => 'not-valid-email',
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/notifications-security', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notifications.email.default_recipient']);
    }

    public function test_prepares_empty_strings_as_null(): void
    {
        $this->actingAs($this->admin);

        $payload = [
            'notifications' => [
                'email' => [
                    'enabled' => true,
                    'default_recipient' => '',  // empty string
                    'subject' => '',  // empty string
                    'body' => '',  // empty string
                ],
            ],
        ];

        $response = $this->putJson('/api/settings/notifications-security', $payload);

        $response->assertOk();

        // Empty strings should be converted to null and pass nullable validation
    }

    public function test_requires_authentication(): void
    {
        $response = $this->putJson('/api/settings/notifications-security', [
            'notifications' => ['email' => ['enabled' => true]],
        ]);

        $response->assertUnauthorized();
    }

    public function test_requires_authorization(): void
    {
        $user = User::factory()->create(['role' => 'investigator']);
        $this->actingAs($user);

        $response = $this->putJson('/api/settings/notifications-security', [
            'notifications' => ['email' => ['enabled' => true]],
        ]);

        $response->assertForbidden();
    }

    public function test_test_notification_requires_authentication(): void
    {
        $response = $this->postJson('/api/settings/notifications/test', [
            'channel' => 'email',
            'target' => 'test@example.com',
        ]);

        $response->assertUnauthorized();
    }
}
