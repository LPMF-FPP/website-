<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_statistics_page_and_chart_data_require_view_permission(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->get(route('statistics.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->getJson(route('statistics.data', ['type' => 'user_origin']))->assertForbidden();
    }

    public function test_statistics_viewer_can_load_the_page_and_chart_data_without_export_permission(): void
    {
        $user = User::factory()->create(['role' => 'analis']);
        $user->markEmailAsVerified();

        $this->actingAs($user)
            ->get(route('statistics.index'))
            ->assertOk()
            ->assertSee('Ringkasan statistik')
            ->assertDontSee('Laporan Periodik')
            ->assertDontSee('<button type="button" data-chart-export=', false)
            ->assertSee('data-chart-empty="suspectGender"', false)
            ->assertSee('role="status" aria-live="polite"', false);

        $this->getJson(route('statistics.data', ['type' => 'user_origin']))->assertOk();
    }

    public function test_chart_export_controls_require_statistics_export_permission(): void
    {
        $user = User::factory()->create(['role' => 'penyelia']);
        $user->markEmailAsVerified();

        $this->assertTrue($user->hasPermission('statistik.export'));

        $this->actingAs($user)
            ->get(route('statistics.index'))
            ->assertOk()
            ->assertSee('data-chart-export="userOrigin"', false)
            ->assertSee('chart.toBase64Image', false);
    }

    public function test_statistics_exporters_are_shown_only_to_export_permission_holders(): void
    {
        $user = User::factory()->create(['role' => 'penyelia']);
        $user->markEmailAsVerified();

        $this->assertTrue($user->hasPermission('statistik.export'));

        $this->actingAs($user)
            ->get(route('statistics.index'))
            ->assertOk()
            ->assertSee('data-chart-export="userOrigin"', false)
            ->assertSee('Unduh PNG');
    }

    public function test_report_tab_without_export_permission_returns_to_the_dashboard_tab(): void
    {
        $user = User::factory()->create(['role' => 'analis']);
        $user->markEmailAsVerified();

        $this->actingAs($user)
            ->get(route('statistics.index', ['tab' => 'reports']))
            ->assertRedirect(route('statistics.index', ['tab' => 'dashboard']));
    }

    public function test_placeholder_chart_export_endpoint_is_not_exposed(): void
    {
        $user = User::factory()->create(['role' => 'analis']);
        $user->markEmailAsVerified();

        $this->actingAs($user)->get('/statistics/export?type=user_origin')->assertNotFound();
    }
}
