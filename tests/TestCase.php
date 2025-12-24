<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear settings cache before each test
        if (function_exists('settings_forget_cache')) {
            settings_forget_cache();
        }
        if (function_exists('settings_fake_clear')) {
            settings_fake_clear();
        }
    }
}
