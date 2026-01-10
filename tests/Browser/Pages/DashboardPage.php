<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page as BasePage;

class DashboardPage extends BasePage
{
    public function url(): string
    {
        return '/dashboard';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url())
            ->assertSee('Dashboard');
    }

    public function elements(): array
    {
        return [
            '@stats' => '.dashboard-stats',
            '@recent-requests' => '.recent-requests',
            '@quick-actions' => '.quick-actions',
            '@notifications' => '.notifications',
        ];
    }

    public function assertStatsVisible(Browser $browser): void
    {
        $browser->assertVisible('@stats');
    }

    public function assertRecentRequestsVisible(Browser $browser): void
    {
        $browser->assertVisible('@recent-requests');
    }

    public function clickQuickAction(Browser $browser, string $action): void
    {
        $browser->within('@quick-actions', function ($browser) use ($action) {
            $browser->clickLink($action);
        });
    }
}
