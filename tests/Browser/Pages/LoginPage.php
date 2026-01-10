<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page as BasePage;

class LoginPage extends BasePage
{
    public function url(): string
    {
        return '/login';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url())
            ->assertSee('Login');
    }

    public function elements(): array
    {
        return [
            '@email' => 'input[name="email"]',
            '@password' => 'input[name="password"]',
            '@submit' => 'button[type="submit"]',
            '@remember' => 'input[name="remember"]',
        ];
    }

    public function login(Browser $browser, string $email, string $password, bool $remember = false): void
    {
        $browser->type('@email', $email)
            ->type('@password', $password);

        if ($remember) {
            $browser->check('@remember');
        }

        $browser->click('@submit')
            ->waitForText('Dashboard');
    }

    public function assertHasValidationError(Browser $browser, string $field): void
    {
        $browser->assertPresent(".error-{$field}");
    }
}
