<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use App\Support\AppTimezone;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;

class TimezoneStabilityTest extends TestCase
{
    use DatabaseTruncation;

    public function test_timezone_preview_bypasses_stale_cache()
    {
        // 1. Initial State: UTC
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Ensure clean state
        settings_fake_clear();
        settings_forget_cache();
        Cache::forget(AppTimezone::CACHE_KEY);
        
        // Set initial timezone to UTC in DB directly
        \App\Models\SystemSetting::create(['key' => 'locale.timezone', 'value' => 'UTC']);
        \App\Models\SystemSetting::create(['key' => 'localization.timezone', 'value' => 'UTC']);
        
        // Verify initial state via API
        $this->actingAs($admin)
            ->getJson('/api/settings/localization/time-preview')
            ->assertJson(['app_timezone' => 'UTC']);
            
        // 2. Update to Asia/Jakarta
        $payload = [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'language' => 'id',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56'
            ],
            'retention' => [
                'storage_driver' => 'public'
            ]
        ];
        
        $this->actingAs($admin)
            ->putJson('/api/settings/localization-retention', $payload)
            ->assertStatus(200);

        // SIMULATE STALE CACHE (The bug condition)
        // Even if controller cleared it, assume something (race condition/other process) re-cached old value
        // or the clear didn't propagate instantly
        Cache::put(AppTimezone::CACHE_KEY, 'UTC', 60);
            
        // 3. Verify immediately after update via time-preview
        // This simulates the "fetchTimePreview" call right after save
        $response = $this->actingAs($admin)
            ->getJson('/api/settings/localization/time-preview');
            
        $response->assertStatus(200);
        
        // This is where we expect it to fail if the bug exists
        $response->assertJson(['app_timezone' => 'Asia/Jakarta']);
        
        // Verify Cache-Control header contains necessary directives
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }
}
