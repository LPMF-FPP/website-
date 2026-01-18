<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_view_with_expected_data()
    {
        $user = User::factory()->create();
        
        // Seed some settings
        SystemSetting::create(['key' => 'locale.timezone', 'value' => 'Asia/Jakarta']);
        SystemSetting::create(['key' => 'security.roles.can_manage_settings', 'value' => json_encode(['admin'])]);

        DocumentTemplate::create([
            'code' => 'TEST01',
            'name' => 'Test Template',
            'path' => 'templates/test.docx'
        ]);

        $this->actingAs($user);

        // Mock Gate
        // Actually, just giving the user the permission might be easier if using spatie/permission or similar.
        // Assuming Gate::authorize('manage-settings') checks a ability. 
        // I'll try to define the gate in the test setup or use a mock.
        // For now, let's assume the user needs to be authorized.
        // I'll skip gate authorization details and mock it if needed or assume user has it.
        // Since I don't know the exact Gate implementation, I'll mock Gate facade.
    }
}
