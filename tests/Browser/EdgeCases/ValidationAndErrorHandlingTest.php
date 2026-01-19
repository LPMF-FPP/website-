<?php

namespace Tests\Browser\EdgeCases;

use App\Models\TestRequest;
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
                ->press('Create Request')
                ->waitForText('The investigator id field is required')
                ->assertSee('required')
                ->assertPathIs('/requests/create');
        });
    }

    public function test_unauthorized_user_cannot_access_admin_pages(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings')
                ->assertDontSee('Settings')
                ->assertPathIsNot('/settings');
        });
    }

    public function test_duplicate_request_number_shows_error(): void
    {
        $user = User::factory()->create();
        $existing = TestRequest::factory()->create(['request_number' => 'REQ-2026-DUPLICATE']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->type('request_number', 'REQ-2026-DUPLICATE')
                ->press('Create Request')
                ->waitForText('already exists')
                ->assertSee('already exists');
        });
    }

    public function test_concurrent_user_modification_handling(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $request = TestRequest::factory()->create(['status' => 'pending']);

        $this->browse(function (Browser $browser1, Browser $browser2) use ($user1, $user2, $request) {
            $browser1->loginAs($user1)
                ->visit("/requests/{$request->id}/edit")
                ->type('status', 'in_progress');

            $browser2->loginAs($user2)
                ->visit("/requests/{$request->id}/edit")
                ->type('status', 'completed')
                ->press('Save')
                ->waitForText('updated');

            $browser1->press('Save')
                ->waitForText('conflict')
                ->assertSee('conflict');
        });
    }

    public function test_network_error_shows_retry_option(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->script('window.addEventListener("offline", () => alert("Network offline"));');

            $browser->script('window.dispatchEvent(new Event("offline"));');
            
            $browser->waitForDialog()
                ->assertDialogOpened('Network offline')
                ->acceptDialog();
        });
    }

    public function test_session_timeout_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard');
                
            $browser->script('document.cookie = "laravel_session=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";');
            
            $browser->visit('/requests/create')
                ->waitForText('Login')
                ->assertPathIs('/login');
        });
    }
}
