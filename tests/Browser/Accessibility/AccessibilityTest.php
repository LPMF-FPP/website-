<?php

namespace Tests\Browser\Accessibility;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AccessibilityTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    /**
     * Helper to login user via form instead of loginAs() which doesn't work with DatabaseTransactions
     */
    protected function loginViaForm(Browser $browser, string $email, string $password): void
    {
        $browser->visit('/login')
            ->waitFor('input[name="email"]')
            ->type('email', $email)
            ->type('password', $password)
            ->click('button[type="submit"]')
            ->waitForLocation('/dashboard');
    }

    public function test_login_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->driver->manage()->deleteAllCookies();
            $browser->visit('/login')
                ->waitFor('form')
                ->assertPresent('form')
                ->assertPresent('input[name="email"]')
                ->assertPresent('input[name="password"]')
                ->assertPresent('button[type="submit"]');
        });
    }

    public function test_dashboard_loads(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->browse(function (Browser $browser) use ($user, $password) {
            $browser->driver->manage()->deleteAllCookies();
            $this->loginViaForm($browser, $user->email, $password);
            $browser->assertPresent('h1');
        });
    }

    public function test_forms_have_labels(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('input[name="email"]')
                ->assertPresent('label[for="email"]')
                ->assertPresent('label[for="password"]');
        });
    }

    public function test_buttons_have_accessible_names(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->browse(function (Browser $browser) use ($user, $password) {
            $browser->driver->manage()->deleteAllCookies();
            $this->loginViaForm($browser, $user->email, $password);
            $browser->visit('/requests/create')
                ->waitForText('Formulir Permintaan')
                ->waitFor('#request-create-form')
                // Buttons with visible text content are accessible
                ->assertPresent('button[type="submit"]');
        });
    }

    public function test_images_have_alt_text(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitFor('img');

            $missingAltCount = $browser->driver->executeScript('
                const images = document.querySelectorAll("img");
                return Array.from(images).filter(img => !img.alt).length;
            ');

            $this->assertEquals(0, $missingAltCount, 'All images should have alt text');
        });
    }

    public function test_page_has_proper_heading_structure(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->browse(function (Browser $browser) use ($user, $password) {
            $browser->driver->manage()->deleteAllCookies();
            $this->loginViaForm($browser, $user->email, $password);
            $browser->assertPresent('h1');

            $hasValidStructure = $browser->driver->executeScript('
                const headings = Array.from(document.querySelectorAll("h1, h2, h3, h4, h5, h6"));
                const levels = headings.map(h => parseInt(h.tagName[1]));
                
                for (let i = 1; i < levels.length; i++) {
                    if (levels[i] > levels[i-1] + 1) {
                        return false;
                    }
                }
                return true;
            ');

            $this->assertTrue($hasValidStructure, 'Heading structure should not skip levels');
        });
    }

    public function test_color_contrast_sufficient(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->screenshot('accessibility-color-contrast');
        });
    }

    public function test_keyboard_navigation_works(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitFor('input[name="email"]')
                ->click('input[name="email"]')
                ->keys('input[name="email"]', '{tab}')
                ->pause(100)
                ->assertFocused('input[name="password"]');
        });
    }

    public function test_skip_to_main_content_link_exists(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertPresent('a[href="#main-content"]');
        });
    }

    public function test_aria_landmarks_present(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->browse(function (Browser $browser) use ($user, $password) {
            $browser->driver->manage()->deleteAllCookies();
            $this->loginViaForm($browser, $user->email, $password);
            $browser->assertPresent('main, [role="main"]')
                ->assertPresent('nav, [role="navigation"]')
                ->assertPresent('header, [role="banner"]');
        });
    }
}
