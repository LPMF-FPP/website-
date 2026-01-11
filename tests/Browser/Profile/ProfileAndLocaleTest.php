<?php

namespace Tests\Browser\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProfileAndLocaleTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_update_profile_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->assertSee('Profile')
                ->type('name', 'New Name')
                ->type('email', 'new@example.com')
                ->press('Save')
                ->assertSee('Profile updated');

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
                ->type('current_password', 'old-password')
                ->type('password', 'new-password123')
                ->type('password_confirmation', 'new-password123')
                ->press('Update Password')
                ->assertSee('Password updated');

            $user->refresh();
            $this->assertTrue(Hash::check('new-password123', $user->password));
        });
    }

    public function test_user_can_switch_locale(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->click('button[aria-label="Change Language"]')
                ->clickLink('Bahasa Indonesia')
                ->waitForText('Dasbor')
                ->assertSee('Dasbor');

            $browser->click('button[aria-label="Ubah Bahasa"]')
                ->clickLink('English')
                ->waitForText('Dashboard')
                ->assertSee('Dashboard');
        });
    }

    public function test_locale_persists_across_sessions(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->click('button[aria-label="Change Language"]')
                ->clickLink('Bahasa Indonesia')
                ->waitForText('Dasbor');

            $browser->visit('/dashboard')
                ->assertSee('Dasbor');
        });
    }

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->press('Delete Account')
                ->type('password', 'password123')
                ->press('Confirm Delete')
                ->assertPathIs('/')
                ->assertGuest();

            $this->assertNull(User::find($user->id));
        });
    }
}
