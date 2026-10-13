<?php

namespace Tests\Browser;

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
}
