<?php

namespace Tests\Browser\Documents;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DocumentGenerationTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_request_show_page_displays_berita_acara_section(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee('Dokumen')
                ->assertSee('Berita Acara Penerimaan Sampel')
                ->assertPresent('#ba-status');
        });
    }

    public function test_berita_acara_status_check_runs_on_page_load(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Berita Acara Penerimaan Sampel')
                // BA status element should update from "Checking..." to a final state
                ->waitUntilMissingText('Checking...')
                ->assertPresent('#ba-status');
        });
    }

    public function test_generate_ba_button_visible_when_no_document(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Berita Acara Penerimaan Sampel')
                ->waitUntilMissingText('Checking...')
                ->assertPresent('#btn-generate-ba');
        });
    }

    public function test_cetak_ba_button_in_header(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee('Cetak BA');
        });
    }

    public function test_request_show_page_has_edit_and_back_buttons(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee('Edit Data')
                ->assertSee('Kembali');
        });
    }
}
