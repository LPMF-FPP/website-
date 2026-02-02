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

    public function test_store_calls_send_generation_notification()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization
        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        // Needed for FormRequest authorization check
        \Illuminate\Support\Facades\Gate::shouldReceive('forUser')->andReturnSelf();
        \Illuminate\Support\Facades\Gate::shouldReceive('check')->andReturn(true);
        \Illuminate\Support\Facades\Gate::shouldReceive('any')->andReturn(true);

        $report = new \App\Models\ConsolidatedReport;
        $report->id = 1;
        $report->period_label = 'Test Period';
        $report->download_url = 'http://example.com/report.pdf';

        $this->mock(ConsolidatedReportService::class, function ($mock) use ($report) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn($report);

            $mock->shouldReceive('sendGenerationNotification')
                ->once()
                ->with($report);
        });

        $data = [
            'period_type' => 'monthly',
            'period_start' => '2023-01-01',
            'period_end' => '2023-01-31',
            'signers' => [
                [
                    'role' => 'Pembuat',
                    'name' => 'Signer 1',
                    'position' => 'Position 1',
                    'nip' => '123',
                ],
                [
                    'role' => 'Pemeriksa',
                    'name' => 'Signer 2',
                    'position' => 'Position 2',
                    'nip' => '456',
                ],
                [
                    'role' => 'Pengesah',
                    'name' => 'Signer 3',
                    'position' => 'Position 3',
                    'nip' => '789',
                ],
            ],
        ];

        $response = $this->postJson(route('consolidated-reports.store'), $data);

        $response->assertStatus(201);
    }
}
