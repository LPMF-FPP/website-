<?php

namespace Tests\Browser\Settings;

use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SettingsManagementTest extends DuskTestCase
{
    use DatabaseTruncation;

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
                ->waitForText('Pengaturan LIMS')
                ->assertSee('Pengaturan LIMS');
        });
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/settings')
                ->assertSee('Akses Ditolak');
        });
    }

    public function test_settings_page_has_sidebar_tabs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->waitForText('Pengaturan LIMS')
                ->waitFor('#tab-numbering')
                ->assertSeeIn('#tab-numbering', 'Penomoran Otomatis')
                ->assertSeeIn('#tab-localization', 'Lokalisasi & Retensi')
                ->assertSeeIn('#tab-branding', 'Branding & PDF')
                ->assertSeeIn('#tab-documents', 'Manajemen Dokumen')
                ->assertSeeIn('#tab-iku', 'Perhitungan IKU');
        });
    }

    public function test_settings_page_shows_numbering_section_by_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->waitForText('Pengaturan LIMS')
                ->waitFor('#tab-numbering')
                ->assertSeeIn('#tab-numbering', 'Penomoran Otomatis');
        });
    }

    public function test_settings_page_has_template_documents_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->waitForText('Pengaturan LIMS')
                ->waitForText('Template Dokumen')
                ->assertSee('Template Dokumen');
        });
    }
}
