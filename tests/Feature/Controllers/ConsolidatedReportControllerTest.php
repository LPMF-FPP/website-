<?php

namespace Tests\Feature\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ConsolidatedReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ConsolidatedReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_passes_default_signers_to_view()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Mock the authorize call on controller to bypass authorization
        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        // Mock Service
        $this->mock(ConsolidatedReportService::class, function ($mock) {
            $mock->shouldReceive('getDefaultSigners')
                ->once()
                ->andReturn([['role' => 'Mocked']]);
        });

        $response = $this->get(route('consolidated-reports.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertViewIs('statistics.partials.consolidated-form');
        $response->assertViewHas('defaultSigners', [['role' => 'Mocked']]);
    }

    /** @test */
    public function save_default_signers_logs_changes()
    {
        Log::shouldReceive('info')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'Default signers updated by user') &&
                   array_key_exists('old', $context) &&
                   array_key_exists('new', $context);
        });

        $user = User::factory()->create();

        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        $oldSigners = [['role' => 'old_role', 'name' => 'Old Name']];
        SystemSetting::create(['key' => 'consolidated_report.default_signers', 'value' => $oldSigners]);

        $newSigners = [['role' => 'new_role', 'name' => 'New Name']];

        $response = $this->actingAs($user)
            ->putJson(route('consolidated-reports.save-default-signers'), ['signers' => $newSigners]);

        $response->assertOk();
    }
}
