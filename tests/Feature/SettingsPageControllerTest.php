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

        // Seed some settings (safe updateOrCreate to prevent duplicate key errors if seeder ran)
        SystemSetting::updateOrCreate(
            ['key' => 'locale.timezone'],
            ['value' => 'Asia/Jakarta']
        );
        SystemSetting::updateOrCreate(
            ['key' => 'security.roles.can_manage_settings'],
            ['value' => json_encode(['admin'])]
        );

        DocumentTemplate::create([
            'code' => 'TEST01',
            'name' => 'Test Template',
            'path' => 'templates/test.docx',
        ]);

        $this->actingAs($user);

        // Define the Gate permission
        \Illuminate\Support\Facades\Gate::define('manage-settings', fn () => true);

        $response = $this->get(route('settings.index'));

        $response->assertOk();
        $response->assertViewIs('settings.index');
        $response->assertViewHas(['settings', 'options', 'templates']);

        // Assert that options contains document_types
        $options = $response->viewData('options');
        $this->assertArrayHasKey('document_types', $options);
    }
}
