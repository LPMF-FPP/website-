<?php

namespace Tests\Browser\Settings;

use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SettingsManagementTest extends DuskTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
    }

    public function test_admin_can_access_settings_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->assertSee('Settings')
                ->assertSee('Branding')
                ->assertSee('Numbering')
                ->assertSee('Localization');
        });
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings')
                ->assertDontSee('Settings');
        });
    }

    public function test_admin_can_update_branding_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->type('settings[branding][org_name]', 'Updated Lab Name')
                ->type('settings[branding][lab_code]', 'ULN')
                ->type('settings[branding][primary_color]', '#FF5733')
                ->press('Save Settings')
                ->assertSee('Settings saved successfully');

            $this->assertEquals('Updated Lab Name', settings('branding.org_name'));
            $this->assertEquals('ULN', settings('branding.lab_code'));
        });
    }

    public function test_admin_can_update_numbering_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->click('a[href="#numbering"]')
                ->type('settings[numbering][lhu][prefix]', 'LHU')
                ->type('settings[numbering][lhu][separator]', '-')
                ->type('settings[numbering][lhu][year_format]', 'YYYY')
                ->press('Save Settings')
                ->assertSee('Settings saved');

            $this->assertEquals('LHU', settings('numbering.lhu.prefix'));
        });
    }

    public function test_admin_can_update_localization_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->click('a[href="#localization"]')
                ->select('settings[localization][timezone]', 'Asia/Jakarta')
                ->select('settings[localization][date_format]', 'DD/MM/YYYY')
                ->select('settings[localization][language]', 'id')
                ->press('Save Settings')
                ->assertSee('Settings saved');

            $this->assertEquals('Asia/Jakarta', settings('localization.timezone'));
            $this->assertEquals('id', settings('localization.language'));
        });
    }

    public function test_settings_preview_functionality(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->type('settings[branding][org_name]', 'Preview Lab')
                ->press('Preview PDF')
                ->waitForText('Preview')
                ->assertSee('Preview');
        });
    }

    public function test_settings_cache_invalidation_after_save(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $oldValue = settings('branding.org_name');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->type('settings[branding][org_name]', 'New Organization Name')
                ->press('Save Settings')
                ->assertSee('Settings saved');
        });

        settings_forget_cache();
        $newValue = settings('branding.org_name');

        $this->assertNotEquals($oldValue, $newValue);
        $this->assertEquals('New Organization Name', $newValue);
    }
}
