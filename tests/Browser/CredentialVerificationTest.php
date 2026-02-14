<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

class CredentialVerificationTest extends DuskTestCase
{
    use DatabaseTruncation;

    /**
     * Test login with valid credentials via the login form.
     */
    public function test_can_login_with_provided_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'dusk-test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browse(function (Browser $browser) {
            try {
                $browser->visit('/login')
                    ->type('email', 'dusk-test@example.com')
                    ->type('password', 'password')
                    ->click('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->assertPathIs('/dashboard');
            } catch (Throwable $e) {
                $browser->screenshot('failure-credential-verification');
                throw $e;
            }
        });
    }
}
