<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_basic_pass_placeholder_is_ignored(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $encrypted = encrypt('secret-pass');
        SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.basic_pass'],
            ['value' => $encrypted]
        );

        $payload = [
            'enabled' => true,
            'base_url' => 'http://localhost:3000',
            'device_id' => 'device-123',
            'basic_user' => 'lpmf',
            'basic_pass' => '••••••••',
            'enabled_milestones' => ['REQUEST_RECEIVED'],
            'templates' => [],
        ];

        $this->actingAs($user)
            ->putJson('/api/settings/notifications/whatsapp', $payload)
            ->assertOk();

        settings_forget_cache();

        $stored = settings('notifications.whatsapp.basic_pass');

        $this->assertSame($encrypted, $stored);
    }
}
