<?php

namespace Tests\Browser\Concerns;

use App\Models\User;
use Laravel\Dusk\Browser;

trait InteractsWithAuth
{
    protected function loginAsRole(Browser $browser, string $role = 'analyst'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $browser->loginAs($user);

        return $user;
    }

    protected function loginAsAdmin(Browser $browser): User
    {
        return $this->loginAsRole($browser, 'admin');
    }

    protected function loginAsAnalyst(Browser $browser): User
    {
        return $this->loginAsRole($browser, 'analyst');
    }

    protected function loginAsLabManager(Browser $browser): User
    {
        return $this->loginAsRole($browser, 'lab_manager');
    }

    protected function performLogin(Browser $browser, string $email, string $password): void
    {
        $browser->visit('/login')
            ->waitForText('Login')
            ->type('email', $email)
            ->type('password', $password)
            ->press('Login')
            ->waitForText('Dashboard');
    }

    protected function performLogout(Browser $browser): void
    {
        $browser->click('@logout-button')
            ->waitForText('Login');
    }

    protected function assertUserHasRole(User $user, string $role): void
    {
        $this->assertEquals($role, $user->role);
    }
}
