<?php

namespace Tests\Browser\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AuthenticationFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_user_can_register_and_verify_account(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->driver->manage()->deleteAllCookies();
            $browser->visit('/register')
                ->assertSee('REGISTER')
                ->type('name', 'John Doe')
                ->type('email', 'john@example.com')
                ->type('password', 'password123')
                ->type('password_confirmation', 'password123')
                ->click('button[type="submit"]')
                ->waitForLocation('/dashboard')
                ->assertAuthenticated();
        });
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->driver->manage()->deleteAllCookies();
            $browser->visit('/login')
                ->waitFor('input[name="email"]')
                ->type('email', $user->email)
                ->type('password', 'password123')
                ->click('button[type="submit"]')
                ->waitForLocation('/dashboard', 10)
                ->assertAuthenticated()
                ->assertAuthenticatedAs($user);
        });
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->driver->manage()->deleteAllCookies();
            $browser->visit('/login')
                ->waitFor('input[name="email"]')
                ->type('email', $user->email)
                ->type('password', 'wrong-password')
                ->click('button[type="submit"]')
                ->waitForText('credentials', 10)
                ->assertPathIs('/login')
                ->assertGuest();
        });
    }

    public function test_complete_authentication_cycle(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->driver->manage()->deleteAllCookies();
            $email = 'jane' . time() . '@example.com';
            
            $browser->visit('/register')
                ->type('name', 'Jane Smith')
                ->type('email', $email)
                ->type('password', 'secure-password')
                ->type('password_confirmation', 'secure-password')
                ->click('button[type="submit"]')
                ->waitForLocation('/dashboard', 10)
                ->assertAuthenticated();

            $browser->click('[data-dropdown-trigger]')
                ->clickLink('Log Out')
                ->assertPathIs('/')
                ->assertGuest();

            $browser->visit('/login')
                ->type('email', $email)
                ->type('password', 'secure-password')
                ->click('button[type="submit"]')
                ->waitForLocation('/dashboard', 10)
                ->assertAuthenticated();
        });
    }

    public function test_user_can_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->driver->manage()->deleteAllCookies();
            $browser->visit('/forgot-password')
                ->assertSee('Forgot your password')
                ->type('email', 'reset@example.com')
                ->press('EMAIL PASSWORD RESET LINK')
                ->assertSee('password reset link');
        });
    }

    public function test_protected_routes_redirect_to_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->driver->manage()->deleteAllCookies();
            $browser->visit('/dashboard')
                ->assertPathIs('/login')
                ->assertGuest();

            $browser->visit('/requests')
                ->assertPathIs('/login')
                ->assertGuest();

            $browser->visit('/settings')
                ->assertPathIs('/login')
                ->assertGuest();
        });
    }
}
