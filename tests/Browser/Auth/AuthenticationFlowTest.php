<?php

namespace Tests\Browser\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AuthenticationFlowTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_register_and_verify_account(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->assertSee('Register')
                ->type('name', 'John Doe')
                ->type('email', 'john@example.com')
                ->type('password', 'password123')
                ->type('password_confirmation', 'password123')
                ->press('Register')
                ->assertPathIs('/dashboard')
                ->assertAuthenticated();
        });
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->assertSee('Email')
                ->type('email', 'user@example.com')
                ->type('password', 'password123')
                ->press('Log in')
                ->assertPathIs('/dashboard')
                ->assertAuthenticated()
                ->assertAuthenticatedAs($user);
        });
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->type('email', 'user@example.com')
                ->type('password', 'wrong-password')
                ->press('Log in')
                ->assertPathIs('/login')
                ->assertGuest()
                ->assertSee('credentials');
        });
    }

    public function test_complete_authentication_cycle(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Jane Smith')
                ->type('email', 'jane@example.com')
                ->type('password', 'secure-password')
                ->type('password_confirmation', 'secure-password')
                ->press('Register')
                ->assertPathIs('/dashboard')
                ->assertAuthenticated();

            $browser->clickLink('Logout')
                ->assertPathIs('/')
                ->assertGuest();

            $browser->visit('/login')
                ->type('email', 'jane@example.com')
                ->type('password', 'secure-password')
                ->press('Log in')
                ->assertPathIs('/dashboard')
                ->assertAuthenticated();
        });
    }

    public function test_user_can_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/forgot-password')
                ->assertSee('Forgot your password')
                ->type('email', 'reset@example.com')
                ->press('Email Password Reset Link')
                ->assertSee('password reset link');
        });
    }

    public function test_protected_routes_redirect_to_login(): void
    {
        $this->browse(function (Browser $browser) {
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
