<?php

namespace Tests\Browser\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MobileResponsiveTest extends DuskTestCase
{
    use DatabaseTransactions;

    protected function configureBrowserForMobile(Browser $browser): void
    {
        $browser->driver->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(375, 812));
    }

    public function test_mobile_navigation_menu(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard')
                ->assertPresent('.mobile-menu-toggle')
                ->click('.mobile-menu-toggle')
                ->waitFor('.mobile-menu')
                ->assertVisible('.mobile-menu');
        });
    }

    public function test_mobile_touch_interactions(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/requests')
                ->waitForText('Requests')
                ->assertPresent('.swipeable-list')
                ->script('
                    const element = document.querySelector(".swipeable-list");
                    const touch = new Touch({
                        identifier: Date.now(),
                        target: element,
                        clientX: 100,
                        clientY: 100
                    });
                    const event = new TouchEvent("touchstart", {
                        touches: [touch],
                        targetTouches: [touch],
                        changedTouches: [touch]
                    });
                    element.dispatchEvent(event);
                ');
        });
    }

    public function test_mobile_responsive_layout(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard')
                ->assertPresent('.container-mobile')
                ->assertMissing('.desktop-sidebar')
                ->assertVisible('.mobile-header');
        });
    }

    public function test_mobile_form_inputs(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Create New Request')
                ->assertAttribute('input[name="request_number"]', 'autocomplete', 'off')
                ->assertPresent('input[type="date"]')
                ->assertVisible('.mobile-friendly-button');
        });
    }
}
