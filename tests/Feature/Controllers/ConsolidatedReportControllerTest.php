<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Services\ConsolidatedReportService;
use Tests\TestCase;

class ConsolidatedReportControllerTest extends TestCase
{
    public function test_index_passes_default_signers_to_view()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Mock the authorize call on controller to bypass authorization
        // We use partial mock of controller logic via Gate or just rely on withoutMiddleware not working for authorize() calls inside controller if they use Gate facade explicitly.
        // Actually, Controller::authorize calls Gate::authorize.

        // Let's force permission via Gate mock, as withoutMiddleware doesn't disable manual authorize calls.
        \Illuminate\Support\Facades\Gate::shouldReceive('authorize')
            ->with('statistik.export', [])
            ->andReturn(true);

        // Mock Service
        $this->mock(ConsolidatedReportService::class, function ($mock) {
            $mock->shouldReceive('getDefaultSigners')
                ->once()
                ->andReturn([['role' => 'Mocked']]);
        });

        // $response = $this->getJson(route('consolidated-reports.index')); // AJAX request
        // getJson sets Accept: application/json, but Laravel's request()->ajax() checks for X-Requested-With: XMLHttpRequest
        // So we need to ensure it's treated as AJAX
        $response = $this->get(route('consolidated-reports.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertViewIs('statistics.partials.consolidated-form');
        $response->assertViewHas('defaultSigners', [['role' => 'Mocked']]);
    }
}
