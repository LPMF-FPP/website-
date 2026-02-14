<?php

namespace Tests\Browser\Search;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SearchAndTrackingTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_public_tracking_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/track')
                ->waitForText('Tracking Pengujian Sampel')
                ->assertSee('Tracking Pengujian Sampel')
                ->assertPresent('input[name="tracking_number"]')
                ->assertSee('Lacak Sekarang');
        });
    }

    public function test_public_tracking_with_invalid_number(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/track')
                ->waitForText('Tracking Pengujian Sampel')
                ->type('tracking_number', 'INVALID-12345')
                ->press('Lacak Sekarang')
                ->waitForText('tidak ditemukan')
                ->assertSee('tidak ditemukan');
        });
    }

    public function test_public_tracking_with_valid_receipt_number(): void
    {
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($request) {
            $browser->visit('/track')
                ->waitForText('Tracking Pengujian Sampel')
                ->type('tracking_number', $request->receipt_number)
                ->press('Lacak Sekarang')
                ->waitForText($request->receipt_number)
                ->assertSee($request->receipt_number);
        });
    }

    public function test_authenticated_search_page_loads(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/search')
                ->waitForText('Pencarian Dokumen')
                ->assertSee('Pencarian Dokumen')
                ->assertPresent('#search-query');
        });
    }

    public function test_authenticated_tracking_page_loads(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/tracking')
                ->waitForText('Tracking Pengujian Sampel')
                ->assertSee('Tracking Pengujian Sampel');
        });
    }
}
