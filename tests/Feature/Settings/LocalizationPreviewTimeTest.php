<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\AppTimezone;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LocalizationPreviewTimeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/settings/localization/time-preview')
            ->assertStatus(401);
    }

    public function test_returns_preview_json_success(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->getJson('/api/settings/localization/time-preview');

        $response->assertOk()
            ->assertJsonStructure([
                'app_timezone',
                'php_timezone',
                'now_app',
                'now_utc',
            ]);
    }

    public function test_time_preview_reflects_setting_timezone(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        SystemSetting::updateOrCreate(
            ['key' => 'localization.timezone'],
            ['value' => 'Asia/Jayapura']
        );
        settings_forget_cache();
        Cache::forget(AppTimezone::CACHE_KEY);

        $response = $this->actingAs($user)
            ->getJson('/api/settings/localization/time-preview');

        $response->assertOk()
            ->assertJsonPath('app_timezone', 'Asia/Jayapura');
    }
}
