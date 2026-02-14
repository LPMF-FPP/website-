<?php

namespace Tests\Browser\EdgeCases;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DataIntegrityTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_request_create_form_shows_validation_errors(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

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

    public function test_request_form_preserves_input_on_validation_failure(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Formulir Permintaan Pengujian Sampel')
                ->type('investigator_name', 'Test Investigator')
                ->press('Simpan')
                ->assertPathIs('/requests/create')
                ->assertInputValue('investigator_name', 'Test Investigator');
        });
    }

    public function test_request_count_unchanged_after_validation_failure(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $initialCount = TestRequest::count();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Formulir Permintaan Pengujian Sampel')
                ->press('Simpan')
                ->assertPathIs('/requests/create');
        });

        $this->assertEquals($initialCount, TestRequest::count());
    }
}
