<?php

namespace Tests\Browser\Search;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SearchAndTrackingTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_public_tracking_without_authentication(): void
    {
        $request = TestRequest::factory()->create([
            'tracking_number' => 'TRACK-12345',
            'status' => 'in_progress',
        ]);

        $this->browse(function (Browser $browser) use ($request) {
            $browser->visit('/track')
                ->assertSee('Track Your Request')
                ->type('tracking_number', 'TRACK-12345')
                ->press('Track')
                ->assertSee('TRACK-12345')
                ->assertSee('in_progress');
        });
    }

    public function test_public_tracking_json_endpoint(): void
    {
        $request = TestRequest::factory()->create([
            'tracking_number' => 'TRACK-99999',
        ]);

        $this->browse(function (Browser $browser) use ($request) {
            $browser->visit('/track/TRACK-99999.json')
                ->assertSee('TRACK-99999');
        });
    }

    public function test_authenticated_search_functionality(): void
    {
        $user = User::factory()->create();
        TestRequest::factory()->count(5)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/search')
                ->assertSee('Search')
                ->type('q', 'REQ')
                ->keys('input[name="q"]', '{enter}')
                ->waitForText('Results')
                ->assertSee('Results');
        });
    }

    public function test_search_suggestions(): void
    {
        $user = User::factory()->create();
        TestRequest::factory()->create(['request_letter_number' => 'REQ-2026-001']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/search')
                ->type('q', 'REQ-202')
                ->waitForText('REQ-2026-001')
                ->assertSee('REQ-2026-001');
        });
    }

    public function test_search_filters_and_sorting(): void
    {
        $user = User::factory()->create();
        TestRequest::factory()->count(10)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/search')
                ->select('filter[status]', 'pending')
                ->select('sort', 'created_at_desc')
                ->press('Apply Filters')
                ->waitForText('Results')
                ->assertSee('Results');
        });
    }
}
