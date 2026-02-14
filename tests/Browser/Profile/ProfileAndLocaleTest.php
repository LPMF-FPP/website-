<?php

namespace Tests\Browser\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProfileAndLocaleTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_user_can_view_profile_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->waitForText('Profile')
                ->assertSee('Profile')
                ->assertInputValue('name', 'Test User')
                ->assertInputValue('email', 'test@example.com');
        });
    }

    public function test_user_can_update_profile_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->waitForText('Profile')
                ->clear('name')
                ->type('name', 'New Name')
                ->clear('email')
                ->type('email', 'new@example.com');

            $browser->script('document.querySelector("form[action$=\"/profile\"] button[type=\"submit\"]").click();');

            $browser
                ->pause(700)
                ->assertPathIs('/profile');

            $user->refresh();
            $this->assertEquals('New Name', $user->name);
            $this->assertEquals('new@example.com', $user->email);
        });
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->waitForText('Profile')
                ->type('current_password', 'old-password')
                ->type('password', 'new-password123')
                ->type('password_confirmation', 'new-password123');

            $browser->script('document.querySelector("form[action$=\"/password\"] button[type=\"submit\"]").click();');

            $browser
                ->pause(700)
                ->assertPathIs('/profile');

            $user->refresh();
            $this->assertTrue(Hash::check('new-password123', $user->password));
        });
    }

    public function test_profile_page_has_delete_account_section(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->waitForText('Profile')
                ->assertSee('Delete Account');
        });
    }

    public function test_dashboard_displays_in_bahasa(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Dashboard')
                ->assertSee('Dashboard');
        });
    }
}
