<?php

namespace Tests\Browser\Visual;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class VisualRegressionTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_dashboard_visual_regression(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard')
                ->screenshot('dashboard-visual-baseline');
        });
    }

    public function test_login_page_visual_regression(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/login')
                ->waitFor('input[name="email"]')
                ->screenshot('login-page-visual-baseline');
        });
    }

    public function test_settings_page_visual_regression(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->waitForText('Pengaturan LIMS')
                ->screenshot('settings-page-visual-baseline');
        });
    }

    public function test_request_create_form_visual_regression(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Formulir Permintaan Pengujian Sampel')
                ->screenshot('request-create-form-visual-baseline');
        });
    }
}
