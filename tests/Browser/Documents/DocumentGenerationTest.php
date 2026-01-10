<?php

namespace Tests\Browser\Documents;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DocumentGenerationTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_generate_berita_acara(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->assertPresent('button:contains("Generate Berita Acara")')
                ->press('Generate Berita Acara')
                ->waitForText('Document generated successfully')
                ->assertSee('Document generated successfully')
                ->assertSee($request->request_number);
        });
    }

    public function test_user_can_download_generated_documents(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->assertPresent('button:contains("Generate Berita Acara")')
                ->press('Generate Berita Acara')
                ->waitForText('Document generated successfully')
                ->waitForLink('Download Berita Acara')
                ->assertPresent('a:contains("Download Berita Acara")')
                ->clickLink('Download Berita Acara');
        });
    }

    public function test_user_can_view_document_in_browser(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->assertPresent('button:contains("Generate Berita Acara")')
                ->press('Generate Berita Acara')
                ->waitForText('Document generated successfully')
                ->waitForLink('View Berita Acara')
                ->assertPresent('a:contains("View Berita Acara")')
                ->clickLink('View Berita Acara')
                ->waitForText('Berita Acara')
                ->assertSee('Berita Acara')
                ->assertSee($request->request_number);
        });
    }

    public function test_user_can_delete_generated_documents(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->assertPresent('button:contains("Generate Berita Acara")')
                ->press('Generate Berita Acara')
                ->waitForText('Document generated successfully')
                ->assertPresent('button:contains("Delete Document")')
                ->press('Delete Document')
                ->acceptDialog()
                ->waitForText('Document deleted successfully')
                ->assertSee('Document deleted successfully')
                ->assertDontSee('Download Berita Acara');
        });
    }

    public function test_document_generation_respects_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $user, $request) {
            $browser->loginAs($admin)
                ->visit('/settings')
                ->assertPresent('input[name="settings[branding][org_name]"]')
                ->type('settings[branding][org_name]', 'Custom Lab Name')
                ->press('Save Settings')
                ->waitForText('Settings saved')
                ->assertSee('Settings saved');

            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->assertPresent('button:contains("Generate Berita Acara")')
                ->press('Generate Berita Acara')
                ->waitForText('Document generated successfully')
                ->waitForLink('View Berita Acara')
                ->clickLink('View Berita Acara')
                ->waitForText('Custom Lab Name')
                ->assertSee('Custom Lab Name')
                ->assertSee($request->request_number);
        });
    }
}
