<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

class CredentialVerificationTest extends DuskTestCase
{
    /**
     * Test login with provided credentials.
     */
    public function test_can_login_with_provided_credentials(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $browser->visit('/login')
                    ->type('email', 'labmutufarmapol@gmail.com')
                    ->type('password', 'LPMFJaya1')
                    ->click('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10) // Wait up to 10s for redirect
                    ->assertPathIs('/dashboard');
            } catch (Throwable $e) {
                $browser->screenshot('failure-credential-verification');
                throw $e;
            }
        });
    }
}
