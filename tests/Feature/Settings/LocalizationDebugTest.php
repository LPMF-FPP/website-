<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationDebugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_can_update_timezone_and_verify_persistence()
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Verify initial state (or set it)
        SystemSetting::updateOrCreate(['key' => 'locale.timezone'], ['value' => 'UTC']);
        SystemSetting::updateOrCreate(['key' => 'localization.timezone'], ['value' => 'UTC']);
        settings_forget_cache();

        $payload = [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'public',
            ],
        ];

        $response = $this->actingAs($user)
            ->putJson('/api/settings/localization-retention', $payload);

        $response->assertOk();

        // Check response has correct timezone
        $response->assertJsonPath('localization.timezone', 'Asia/Jakarta');

        // Check Database - locale.timezone should be updated
        $localeTimezone = SystemSetting::where('key', 'locale.timezone')->first();
        $this->assertNotNull($localeTimezone, 'locale.timezone key should exist');
        $this->assertEquals('Asia/Jakarta', $localeTimezone->value, 'locale.timezone should be Asia/Jakarta');

        // Check Database - localization.timezone should also be updated
        $localizationTimezone = SystemSetting::where('key', 'localization.timezone')->first();
        $this->assertNotNull($localizationTimezone, 'localization.timezone key should exist');
        $this->assertEquals('Asia/Jakarta', $localizationTimezone->value, 'localization.timezone should be Asia/Jakarta');

        // Also verify via settings() helper after cache clear
        settings_forget_cache();
        $this->assertEquals('Asia/Jakarta', settings('locale.timezone'), 'settings(locale.timezone) should return Asia/Jakarta');
        $this->assertEquals('Asia/Jakarta', settings('localization.timezone'), 'settings(localization.timezone) should return Asia/Jakarta');
    }
}
