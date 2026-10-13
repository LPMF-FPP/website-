<?php

namespace Tests\Browser;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Dusk\Browser;
use Tests\Concerns\RecreatesApprovedBrowserDatabase;
use Tests\DuskTestCase;

class StatisticsDashboardTest extends DuskTestCase
{
    use RecreatesApprovedBrowserDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recreateApprovedBrowserDatabase();
        $this->seed(PermissionSeeder::class);
    }

    public function test_statistics_dashboard_shows_empty_states_and_keeps_light_and_dark_themes_readable(): void
    {
        $viewer = User::factory()->create(['role' => 'analis']);

        $this->browse(function (Browser $browser) use ($viewer): void {
            $browser->loginAs($viewer)
                ->visit(route('statistics.index'))
                ->waitForText('Statistik dan rekap data')
                ->waitFor('[data-chart-empty="suspectGender"]:not(.hidden)')
                ->assertSee('Belum ada data jenis kelamin pada permintaan.')
                ->waitFor('[data-chart-empty="monthlyRequests"]:not(.hidden)')
                ->assertSee('Belum ada permintaan pada periode grafik.')
                ->assertDontSee('Unduh PNG')
                ->assertAttribute('[role="group"][aria-label="Tampilan statistik"] button:first-child', 'aria-pressed', 'true');

            $browser->script('window.__setTheme("light");');
            $browser->pause(450);
            $light = $browser->script('const title = document.querySelector(".statistics-page-header h2"); const surface = document.querySelector(".statistics-tabs"); return { title: getComputedStyle(title).color, surface: getComputedStyle(surface).backgroundColor, overflow: document.documentElement.scrollWidth > innerWidth };');
            $this->assertFalse($light[0]['overflow']);

            $browser->script('window.__setTheme("dark");');
            $browser->pause(450);
            $dark = $browser->script('const title = document.querySelector(".statistics-page-header h2"); const surface = document.querySelector(".statistics-tabs"); return { title: getComputedStyle(title).color, surface: getComputedStyle(surface).backgroundColor };');

            $this->assertNotSame($light[0]['title'], $dark[0]['title']);
            $this->assertNotSame($light[0]['surface'], $dark[0]['surface']);
        });
    }

    public function test_populated_charts_render_and_remain_available_after_theme_changes(): void
    {
        $viewer = User::factory()->create(['role' => 'penyelia']);
        $investigator = Investigator::factory()->create(['jurisdiction' => 'WILAYAH UJI REGRESI']);
        $request = TestRequest::factory()->create(['investigator_id' => $investigator->id]);
        Sample::factory()->create(['test_request_id' => $request->id]);

        $this->browse(function (Browser $browser) use ($viewer): void {
            $browser->loginAs($viewer)
                ->visit(route('statistics.index'))
                ->waitFor('#userOriginChart:not(.hidden)')
                ->waitFor('#monthlyRequestsChart:not(.hidden)')
                ->waitFor('#monthlySamplesChart:not(.hidden)')
                ->assertEnabled('[data-chart-export="userOrigin"]')
                ->assertEnabled('[data-chart-export="monthlyRequests"]')
                ->assertEnabled('[data-chart-export="monthlySamples"]');

            foreach (['light', 'dark', 'light'] as $theme) {
                $browser->script('window.__setTheme("'.$theme.'");');
                $browser->pause(450);
                $state = $browser->script('return { theme: document.documentElement.classList.contains("dark") ? "dark" : "light", color: charts.userOrigin.options.plugins.legend.labels.color, counts: [charts.userOrigin.data.datasets[0].data[0], charts.monthlyRequests.data.datasets[0].data.reduce((sum, value) => sum + value, 0), charts.monthlySamples.data.datasets[0].data.reduce((sum, value) => sum + value, 0)] };');
                $this->assertSame($theme, $state[0]['theme']);
                $this->assertSame($theme === 'dark' ? '#e2e8f0' : '#334155', $state[0]['color']);
                $this->assertSame([1, 1, 1], $state[0]['counts']);
                $browser->assertVisible('#userOriginChart')
                    ->assertVisible('#monthlyRequestsChart')
                    ->assertVisible('#monthlySamplesChart')
                    ->assertNotVisible('[data-chart-error="userOrigin"]')
                    ->assertEnabled('[data-chart-export="userOrigin"]');
            }
        });
    }
}
