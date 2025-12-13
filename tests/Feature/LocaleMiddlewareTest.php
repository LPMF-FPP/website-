<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocaleMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        settings_fake_clear();
        settings_forget_cache();
    }

    public function test_applies_timezone_and_locale_from_settings(): void
    {
        settings_fake([
            'locale.timezone' => 'Asia/Jayapura',
            'locale.language' => 'id',
        ], replace: true);

        Route::get('/_locale-test', fn () => 'OK');
        $this->get('/_locale-test')->assertOk();

        // Middleware should have run via bootstrap; ensure fallback by manual invocation too
        (new \App\Http\Middleware\ApplyLocaleFromSettings())->handle(request(), fn($r) => $r);

        $this->assertSame('Asia/Jayapura', config('app.timezone'));
        $this->assertSame('id', app()->getLocale());
    }

    public function test_fmt_date_and_number_helpers(): void
    {
        settings_fake([
            'locale.number_format' => '1.234,56',
            'locale.date_format' => 'DD/MM/YYYY',
        ], replace: true);

        $this->assertSame('09/10/2025', fmt_date('2025-10-09'));
        $this->assertSame('12.345,68', fmt_number(12345.678, 2));
    }
}
