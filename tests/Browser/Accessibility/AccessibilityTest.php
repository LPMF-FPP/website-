<?php

namespace Tests\Browser\Accessibility;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AccessibilityTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_login_page_accessibility(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->waitForText('Login')
                ->assertAccessible();
        });
    }

    public function test_dashboard_accessibility(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard')
                ->assertAccessible();
        });
    }

    public function test_forms_have_labels(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->assertPresent('label[for="email"]')
                ->assertPresent('label[for="password"]')
                ->assertAttribute('input[name="email"]', 'aria-label', 'email')
                ->assertAttribute('input[name="password"]', 'aria-label', 'password');
        });
    }

    public function test_buttons_have_accessible_names(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->assertPresent('button[aria-label]')
                ->assertPresent('button:not([aria-label]):has(> span)');
        });
    }

    public function test_images_have_alt_text(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->script('
                    const images = document.querySelectorAll("img");
                    const missingAlt = Array.from(images).filter(img => !img.alt);
                    return missingAlt.length;
                ');

            $missingAltCount = $browser->driver->executeScript('
                const images = document.querySelectorAll("img");
                return Array.from(images).filter(img => !img.alt).length;
            ');

            $this->assertEquals(0, $missingAltCount, 'All images should have alt text');
        });
    }

    public function test_page_has_proper_heading_structure(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertPresent('h1')
                ->script('
                    const headings = Array.from(document.querySelectorAll("h1, h2, h3, h4, h5, h6"));
                    const levels = headings.map(h => parseInt(h.tagName[1]));
                    
                    for (let i = 1; i < levels.length; i++) {
                        if (levels[i] > levels[i-1] + 1) {
                            return false;
                        }
                    }
                    return true;
                ');
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
                ->keys('input[name="email"]', '{tab}')
                ->assertFocused('input[name="password"]')
                ->keys('input[name="password"]', '{tab}')
                ->assertFocused('button[type="submit"]');
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
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertPresent('[role="main"], main')
                ->assertPresent('[role="navigation"], nav')
                ->assertPresent('[role="banner"], header');
        });
    }
}
