<?php

namespace Tests\Browser\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MobileResponsiveTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected function configureBrowserForMobile(Browser $browser): void
    {
        $browser->driver->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(375, 812));
    }

    public function test_mobile_shows_hamburger_menu(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard')
                ->assertPresent('button.xl\\:hidden');
        });
    }

    public function test_mobile_responsive_layout_hides_desktop_nav(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard');

            // Desktop nav (hidden xl:flex) should be hidden at mobile width
            $isDesktopNavHidden = $browser->script(
                'return window.getComputedStyle(document.querySelector(".hidden.xl\\\\:flex")).display === "none";'
            );
            $this->assertTrue($isDesktopNavHidden[0] ?? false, 'Desktop nav should be hidden at mobile width');
        });
    }

    public function test_mobile_dashboard_displays_correctly(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard')
                ->assertSee('Dashboard');
        });
    }

    public function test_mobile_request_create_form_accessible(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->configureBrowserForMobile($browser);

            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Formulir Permintaan Pengujian Sampel')
                ->assertSee('Formulir Permintaan Pengujian Sampel')
                ->assertPresent('input[name="investigator_name"]');
        });
    }
}
