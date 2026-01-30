<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF for all tests
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        // Clear settings cache before each test
        if (function_exists('settings_forget_cache')) {
            settings_forget_cache();
        }
        if (function_exists('settings_fake_clear')) {
            settings_fake_clear();
        }
    }
}
