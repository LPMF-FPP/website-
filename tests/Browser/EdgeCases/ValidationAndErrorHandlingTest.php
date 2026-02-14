<?php

namespace Tests\Browser\EdgeCases;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ValidationAndErrorHandlingTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_form_shows_validation_errors_on_empty_submission(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Formulir Permintaan Pengujian Sampel')
                ->press('Simpan')
                ->assertPathIs('/requests/create');

            $invalidCount = $browser->script('return document.querySelectorAll(":invalid").length;');
            $this->assertNotEmpty($invalidCount);
            $this->assertGreaterThan(0, (int) $invalidCount[0]);
        });
    }

    public function test_unauthorized_user_cannot_access_settings(): void
    {
        $user = User::factory()->create(['role' => 'investigator']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings')
                ->assertSee('Akses Ditolak');
        });
    }

    public function test_network_error_shows_retry_option(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Formulir Permintaan Pengujian Sampel')
                ->script('window.addEventListener("offline", () => alert("Network offline"));');

            $browser->script('window.dispatchEvent(new Event("offline"));');

            $browser->waitForDialog()
                ->assertDialogOpened('Network offline')
                ->acceptDialog();
        });
    }

    public function test_session_timeout_redirects_to_login(): void
    {
        $password = 'dusk-password-123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->browse(function (Browser $browser) use ($user, $password) {
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/login')
                ->waitFor('input[name="email"]')
                ->type('email', $user->email)
                ->type('password', $password)
                ->click('button[type="submit"]')
                ->waitForLocation('/dashboard')
                ->waitForText('Dashboard');

            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/requests/create')
                ->waitFor('input[name="email"]')
                ->assertPathIs('/login');
        });
    }

    public function test_request_edit_page_warns_about_ba_regeneration(): void
    {
        $user = User::factory()->create();
        $request = \App\Models\TestRequest::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}/edit")
                ->waitForText('Edit Permintaan Pengujian')
                ->assertSee('Berita Acara Penerimaan mungkin perlu di-generate ulang');
        });
    }
}
