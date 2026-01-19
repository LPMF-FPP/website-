<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\WhatsApp\GowaClient;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use Tests\TestCase;

class WhatsAppConnectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_check_connection_success(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Mock GowaClient
        $this->mock(GowaClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('listDevicesWithCredentials')
                ->once()
                ->with('http://custom-url.com', 'custom-user', 'custom-pass')
                ->andReturn([
                    'success' => true,
                    'devices' => [
                        ['id' => 'dev1', 'name' => 'Device 1']
                    ],
                ]);
        });

        $payload = [
            'base_url' => 'http://custom-url.com',
            'basic_user' => 'custom-user',
            'basic_pass' => 'custom-pass',
        ];

        $this->actingAs($user)
            ->postJson('/api/settings/notifications/whatsapp/check-connection', $payload)
            ->assertOk()
            ->assertJson([
                'message' => 'Connection successful',
                'devices' => [
                    ['id' => 'dev1', 'name' => 'Device 1']
                ]
            ]);
    }

    public function test_check_connection_failed(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Mock GowaClient
        $this->mock(GowaClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('listDevicesWithCredentials')
                ->once()
                ->andReturn([
                    'success' => false,
                    'error' => 'Connection refused',
                    'status' => 500
                ]);
        });

        $payload = [
            'base_url' => 'http://bad-url.com',
            'basic_user' => 'user',
            'basic_pass' => 'pass',
        ];

        $this->actingAs($user)
            ->postJson('/api/settings/notifications/whatsapp/check-connection', $payload)
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Connection failed',
                'error' => 'Connection refused',
            ]);
    }

    public function test_check_connection_uses_stored_password_if_masked(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Set up stored password
        $encrypted = encrypt('real-secret-pass');
        \App\Models\SystemSetting::updateOrCreate(
            ['key' => 'notifications.whatsapp.basic_pass'],
            ['value' => $encrypted]
        );
        settings_forget_cache();

        // Mock GowaClient
        $this->mock(GowaClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('listDevicesWithCredentials')
                ->once()
                ->with('http://localhost:3000', 'lpmf', 'real-secret-pass')
                ->andReturn(['success' => true, 'devices' => []]);
        });

        $payload = [
            'base_url' => 'http://localhost:3000',
            'basic_user' => 'lpmf',
            'basic_pass' => '••••••••', // Masked
        ];

        $this->actingAs($user)
            ->postJson('/api/settings/notifications/whatsapp/check-connection', $payload)
            ->assertOk();
    }
}
