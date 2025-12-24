<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_numbering_current_returns_values(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->getJson('/api/settings/numbering/current');

        $response->assertOk()
            ->assertJsonStructure([
                'sample_code' => ['current', 'next', 'pattern'],
                'ba' => ['current', 'next', 'pattern'],
                'lhu' => ['current', 'next', 'pattern'],
                'ba_penyerahan' => ['current', 'next', 'pattern'],
                'tracking' => ['current', 'next', 'pattern'],
            ]);

        // Verify all values are strings or null, not objects
        $data = $response->json();
        foreach (['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking'] as $scope) {
            $this->assertIsArray($data[$scope], "Scope {$scope} should be an array");
            $this->assertArrayHasKey('current', $data[$scope], "Scope {$scope} should have 'current' key");
            $this->assertArrayHasKey('next', $data[$scope], "Scope {$scope} should have 'next' key");
            $this->assertArrayHasKey('pattern', $data[$scope], "Scope {$scope} should have 'pattern' key");
            
            // current can be null (not issued yet) or string
            if ($data[$scope]['current'] !== null) {
                $this->assertIsString($data[$scope]['current'], "Scope {$scope}.current should be string or null");
            }
            
            // next should always be a string
            $this->assertIsString($data[$scope]['next'], "Scope {$scope}.next should be string");
            $this->assertIsString($data[$scope]['pattern'], "Scope {$scope}.pattern should be string");
        }
    }

    public function test_numbering_preview_returns_example(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson('/api/settings/numbering/preview', [
            'scope' => 'sample_code',
            'config' => [
                'sample_code' => ['pattern' => 'LPMF-{YYYY}-{SEQ:3}'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('example', fn ($example) => str_contains($example, 'LPMF-'));
    }
}
